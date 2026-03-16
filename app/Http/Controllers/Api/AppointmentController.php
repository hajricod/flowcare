<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Appointment;
use App\Models\Setting;
use App\Models\Slot;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AppointmentController extends Controller
{
    private const ACTIVE_QUEUE_STATUSES = ['BOOKED', 'CHECKED_IN'];

    /**
     * Create a new appointment for the authenticated customer.
     *
     * Endpoint: POST /api/appointments
     * Auth: CUSTOMER
     *
     * Validates slot availability, assigns a branch queue number for the day,
     * optionally stores an attachment, and returns the created appointment with
     * related slot, service type, branch, and staff details.
     *
     * Responses:
     * - 201: Appointment created
     * - 429: Daily booking limit reached
     * - 422: Validation failed or slot is already full
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'slot_id' => 'required|exists:slots,id',
            'notes' => 'nullable|string',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,gif,pdf|max:10240',
        ]);

        $maxBookingsPerDay = (int) Setting::get('max_bookings_per_customer_per_day', 3);
        if ($maxBookingsPerDay > 0) {
            $todayBookings = Appointment::where('customer_id', $user->id)
                ->whereDate('created_at', today())
                ->count();

            if ($todayBookings >= $maxBookingsPerDay) {
                return response()->json([
                    'message' => "Daily booking limit reached ({$maxBookingsPerDay}).",
                ], 429);
            }
        }

        $slot = Slot::findOrFail($validated['slot_id']);

        $existingCount = Appointment::where('slot_id', $slot->id)
            ->whereIn('status', self::ACTIVE_QUEUE_STATUSES)
            ->count();
        if ($existingCount >= 1) {
            return response()->json(['message' => 'Slot is fully booked.'], 422);
        }

        $todayCount = Appointment::where('branch_id', $slot->branch_id)
            ->whereDate('created_at', today())
            ->count();
        $queueNumber = $todayCount + 1;

        $attachmentPath = null;
        $attachmentOriginalName = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $ext = $file->getClientOriginalExtension();
            $uuid = Str::uuid();
            $attachmentPath = "uploads/appointments/{$uuid}.{$ext}";
            $attachmentOriginalName = $file->getClientOriginalName();
            Storage::disk('local')->putFileAs('uploads/appointments', $file, "{$uuid}.{$ext}");
        }

        try {
            $appointment = Appointment::create([
                'customer_id' => $user->id,
                'branch_id' => $slot->branch_id,
                'service_type_id' => $slot->service_type_id,
                'slot_id' => $slot->id,
                'staff_id' => $slot->staff_id,
                'status' => 'BOOKED',
                'notes' => $validated['notes'] ?? null,
                'attachment_path' => $attachmentPath,
                'attachment_original_name' => $attachmentOriginalName,
                'queue_number' => $queueNumber,
            ]);
        } catch (QueryException $e) {
            if ($this->isActiveSlotConstraintViolation($e)) {
                return response()->json(['message' => 'Slot is fully booked.'], 422);
            }

            throw $e;
        }

        AuditLog::log($user->id, $user->role, 'APPOINTMENT_BOOKED', 'APPOINTMENT', $appointment->id, null, $slot->branch_id);

        $appointment = $appointment->load(['slot', 'serviceType', 'branch', 'staff']);

        return response()->json(['data' => $this->toAppointmentPayload($appointment)], 201);
    }

    #[QueryParameter('term', description: 'Search by appointment status, notes, service type, or branch (case-insensitive).', type: 'string', required: false, example: 'confirmed')]
    #[QueryParameter('page', description: 'Page number.', type: 'integer', required: false, example: 1)]
    #[QueryParameter('size', description: 'Number of records per page.', type: 'integer', required: false, example: 15)]
    /**
     * List appointments for the authenticated customer.
     *
     * Endpoint: GET /api/appointments
     * Auth: CUSTOMER
     *
     * Supports pagination with query parameters:
     * - page (default: 1)
     * - size (default: 15)
    * - term (optional case-insensitive search over status, notes, branch, service)
    *
    * Each appointment includes `live_queue_position` (null when not in an active
    * queue state).
    *
    * Response shape:
    * - `results`: records for the current page
    * - `total`: total matching records
     *
     * Responses:
     * - 200: Paginated appointment list with total count
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Appointment::where('customer_id', $user->id)
            ->with(['slot', 'serviceType', 'branch', 'staff']);

        if ($request->filled('term')) {
            $term = '%' . $request->term . '%';
            $query->where(function ($q) use ($term) {
                $q->where('status', 'ilike', $term)
                    ->orWhere('notes', 'ilike', $term)
                    ->orWhereHas('branch', fn ($b) => $b->where('name', 'ilike', $term)->orWhere('city', 'ilike', $term))
                    ->orWhereHas('serviceType', fn ($s) => $s->where('name', 'ilike', $term));
            });
        }

        $perPage = (int) $request->query('size', 15);
        $results = $query->paginate($perPage, ['*'], 'page', $request->query('page', 1));

        $payload = collect($results->items())
            ->map(fn (Appointment $appointment) => $this->toAppointmentPayload($appointment))
            ->values();

        return response()->json(['results' => $payload, 'total' => $results->total()]);
    }

    /**
     * Get details of a single customer appointment.
     *
     * Endpoint: GET /api/appointments/{id}
     * Auth: CUSTOMER
     *
     * Returns appointment details and related entities. When an attachment exists,
    * an `attachment_url` field is included for downloading it. Includes
    * `live_queue_position` (null when not in an active queue state).
     *
     * Responses:
     * - 200: Appointment found
     * - 404: Appointment not found for the authenticated customer
     */
    public function show(Request $request, string $id)
    {
        $user = $request->user();
        $appointment = Appointment::where('customer_id', $user->id)
            ->with(['slot', 'serviceType', 'branch', 'staff'])
            ->findOrFail($id);

        $data = $this->toAppointmentPayload($appointment);
        return response()->json(['data' => $data]);
    }

    /**
     * Cancel a customer appointment.
     *
     * Endpoint: DELETE /api/appointments/{id}
     * Auth: CUSTOMER
     *
     * Only appointments in BOOKED or CHECKED_IN status can be cancelled.
     * Cancellation is recorded in the audit log.
     *
     * Responses:
     * - 200: Appointment cancelled
     * - 422: Appointment cannot be cancelled in current status
     * - 404: Appointment not found for the authenticated customer
     */
    public function cancel(Request $request, string $id)
    {
        $user = $request->user();
        $appointment = Appointment::where('customer_id', $user->id)->findOrFail($id);

        if (!in_array($appointment->status, self::ACTIVE_QUEUE_STATUSES, true)) {
            return response()->json(['message' => 'Cannot cancel appointment in current status.'], 422);
        }

        $appointment->update(['status' => 'CANCELLED']);
        AuditLog::log($user->id, $user->role, 'APPOINTMENT_CANCELLED', 'APPOINTMENT', $appointment->id, null, $appointment->branch_id);

        return response()->json(['data' => $this->toAppointmentPayload($appointment->fresh())]);
    }

    /**
     * Reschedule a customer appointment to a new slot.
     *
     * Endpoint: PUT /api/appointments/{id}/reschedule
     * Auth: CUSTOMER
     *
     * Request body:
     * - new_slot_id (required)
     *
     * Validates that the new slot exists and is not fully booked, then updates
     * slot, staff, branch, and service type references on the appointment.
     *
     * Responses:
     * - 200: Appointment rescheduled
    * - 429: Daily reschedule limit reached
     * - 422: Validation failed or new slot is fully booked
     * - 404: Appointment not found for the authenticated customer
     */
    public function reschedule(Request $request, string $id)
    {
        $user = $request->user();
        $appointment = Appointment::where('customer_id', $user->id)->findOrFail($id);

        $validated = $request->validate(['new_slot_id' => 'required|exists:slots,id']);

        $newSlot = Slot::findOrFail($validated['new_slot_id']);

        $maxReschedulesPerDay = (int) Setting::get('max_reschedules_per_appointment_per_day', 2);
        if ($maxReschedulesPerDay > 0) {
            $todayReschedules = AuditLog::where('actor_id', $user->id)
                ->where('action_type', 'APPOINTMENT_RESCHEDULED')
                ->where('entity_type', 'APPOINTMENT')
                ->where('entity_id', $appointment->id)
                ->whereDate('created_at', today())
                ->count();

            if ($todayReschedules >= $maxReschedulesPerDay) {
                return response()->json([
                    'message' => "Daily reschedule limit reached ({$maxReschedulesPerDay}) for this appointment.",
                ], 429);
            }
        }

        $existingCount = Appointment::where('slot_id', $newSlot->id)
            ->whereIn('status', self::ACTIVE_QUEUE_STATUSES)
            ->count();
        if ($existingCount >= 1) {
            return response()->json(['message' => 'New slot is fully booked.'], 422);
        }

        try {
            $appointment->update([
                'slot_id' => $newSlot->id,
                'staff_id' => $newSlot->staff_id,
                'branch_id' => $newSlot->branch_id,
                'service_type_id' => $newSlot->service_type_id,
            ]);
        } catch (QueryException $e) {
            if ($this->isActiveSlotConstraintViolation($e)) {
                return response()->json(['message' => 'New slot is fully booked.'], 422);
            }

            throw $e;
        }

        AuditLog::log($user->id, $user->role, 'APPOINTMENT_RESCHEDULED', 'APPOINTMENT', $appointment->id, ['new_slot_id' => $newSlot->id], $appointment->branch_id);

        $appointment = $appointment->fresh()->load(['slot', 'serviceType', 'branch', 'staff']);

        return response()->json(['data' => $this->toAppointmentPayload($appointment)]);
    }

    /**
     * Download an appointment attachment.
     *
     * Endpoint: GET /api/appointments/{id}/attachment
     * Auth: CUSTOMER, STAFF, BRANCH_MANAGER, ADMIN
     *
     * Access rules:
     * - Customer can download only their own appointment attachment
    * - Staff can download only attachments for appointments assigned to them
    * - Branch manager can download only attachments from their branch
    * - Admin can download any appointment attachment
     *
     * Responses:
     * - 200: File download
     * - 403: Forbidden by ownership/scope rules
     * - 404: Appointment or attachment not found
     */
    public function getAttachment(Request $request, string $id)
    {
        $user = $request->user();
        $appointment = Appointment::findOrFail($id);

        if ($user->isCustomer() && $appointment->customer_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($user->isStaff() && $appointment->staff_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($user->isBranchManager() && $appointment->branch_id !== $user->branch_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (!$appointment->attachment_path || !Storage::disk('local')->exists($appointment->attachment_path)) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        return Storage::disk('local')->download($appointment->attachment_path, $appointment->attachment_original_name);
    }

    private function isActiveSlotConstraintViolation(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? null;
        $message = $e->getMessage();

        return $sqlState === '23505'
            && str_contains($message, 'appointments_active_slot_unique');
    }

    private function toAppointmentPayload(Appointment $appointment): array
    {
        $data = $appointment->toArray();

        if ($appointment->attachment_path) {
            $data['attachment_url'] = url("/api/appointments/{$appointment->id}/attachment");
        }

        $data['live_queue_position'] = $this->calculateLiveQueuePosition($appointment);

        return $data;
    }

    private function calculateLiveQueuePosition(Appointment $appointment): ?int
    {
        if (!in_array($appointment->status, self::ACTIVE_QUEUE_STATUSES, true)) {
            return null;
        }

        if (! $appointment->branch_id || ! $appointment->queue_number || ! $appointment->created_at) {
            return null;
        }

        $queueDate = $appointment->created_at->toDateString();

        return Appointment::where('branch_id', $appointment->branch_id)
            ->whereDate('created_at', $queueDate)
            ->whereIn('status', self::ACTIVE_QUEUE_STATUSES)
            ->where('queue_number', '<=', $appointment->queue_number)
            ->count();
    }
}

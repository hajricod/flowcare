<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Appointment;
use App\Models\Slot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AppointmentController extends Controller
{
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

        $slot = Slot::findOrFail($validated['slot_id']);

        $existingCount = Appointment::where('slot_id', $slot->id)
            ->whereIn('status', ['BOOKED', 'CHECKED_IN'])
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

        AuditLog::log($user->id, $user->role, 'APPOINTMENT_BOOKED', 'APPOINTMENT', $appointment->id, null, $slot->branch_id);

        return response()->json(['data' => $appointment->load(['slot', 'serviceType', 'branch', 'staff'])], 201);
    }

    /**
     * List appointments for the authenticated customer.
     *
     * Endpoint: GET /api/appointments
     * Auth: CUSTOMER
     *
     * Supports pagination with query parameters:
     * - page (default: 1)
     * - size (default: 15)
     *
     * Responses:
     * - 200: Paginated appointment list with total count
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $perPage = (int) $request->query('size', 15);
        $results = Appointment::where('customer_id', $user->id)
            ->with(['slot', 'serviceType', 'branch', 'staff'])
            ->paginate($perPage, ['*'], 'page', $request->query('page', 1));
        return response()->json(['data' => $results->items(), 'total' => $results->total()]);
    }

    /**
     * Get details of a single customer appointment.
     *
     * Endpoint: GET /api/appointments/{id}
     * Auth: CUSTOMER
     *
     * Returns appointment details and related entities. When an attachment exists,
     * an `attachment_url` field is included for downloading it.
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

        $data = $appointment->toArray();
        if ($appointment->attachment_path) {
            $data['attachment_url'] = url("/api/appointments/{$id}/attachment");
        }
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

        if (!in_array($appointment->status, ['BOOKED', 'CHECKED_IN'])) {
            return response()->json(['message' => 'Cannot cancel appointment in current status.'], 422);
        }

        $appointment->update(['status' => 'CANCELLED']);
        AuditLog::log($user->id, $user->role, 'APPOINTMENT_CANCELLED', 'APPOINTMENT', $appointment->id, null, $appointment->branch_id);

        return response()->json(['data' => $appointment]);
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
     * - 422: Validation failed or new slot is fully booked
     * - 404: Appointment not found for the authenticated customer
     */
    public function reschedule(Request $request, string $id)
    {
        $user = $request->user();
        $appointment = Appointment::where('customer_id', $user->id)->findOrFail($id);

        $validated = $request->validate(['new_slot_id' => 'required|exists:slots,id']);

        $newSlot = Slot::findOrFail($validated['new_slot_id']);
        $existingCount = Appointment::where('slot_id', $newSlot->id)
            ->whereIn('status', ['BOOKED', 'CHECKED_IN'])
            ->count();
        if ($existingCount >= 1) {
            return response()->json(['message' => 'New slot is fully booked.'], 422);
        }

        $appointment->update([
            'slot_id' => $newSlot->id,
            'staff_id' => $newSlot->staff_id,
            'branch_id' => $newSlot->branch_id,
            'service_type_id' => $newSlot->service_type_id,
        ]);

        AuditLog::log($user->id, $user->role, 'APPOINTMENT_RESCHEDULED', 'APPOINTMENT', $appointment->id, ['new_slot_id' => $newSlot->id], $appointment->branch_id);

        return response()->json(['data' => $appointment->fresh()->load(['slot', 'serviceType', 'branch', 'staff'])]);
    }

    /**
     * Download an appointment attachment.
     *
     * Endpoint: GET /api/appointments/{id}/attachment
     * Auth: CUSTOMER, STAFF, BRANCH_MANAGER, ADMIN
     *
     * Access rules:
     * - Customer can download only their own appointment attachment
     * - Staff/Manager/Admin can download based on route role access
     *
     * Responses:
     * - 200: File download
     * - 403: Customer is not owner of the appointment
     * - 404: Appointment or attachment not found
     */
    public function getAttachment(Request $request, string $id)
    {
        $user = $request->user();
        $appointment = Appointment::findOrFail($id);

        if ($user->isCustomer() && $appointment->customer_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (!$appointment->attachment_path || !Storage::disk('local')->exists($appointment->attachment_path)) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        return Storage::disk('local')->download($appointment->attachment_path, $appointment->attachment_original_name);
    }
}

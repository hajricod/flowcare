<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Appointment;
use App\Models\ServiceType;
use App\Models\Setting;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SlotController extends Controller
{
    private function assertManagerBranchScope($user, string $branchId): ?\Illuminate\Http\JsonResponse
    {
        if ($user->isBranchManager() && $user->branch_id !== $branchId) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return null;
    }

    private function assertSlotRelations(string $branchId, string $serviceTypeId, ?string $staffId): ?\Illuminate\Http\JsonResponse
    {
        $service = ServiceType::find($serviceTypeId);
        if (!$service || $service->branch_id !== $branchId) {
            return response()->json(['message' => 'Service type must belong to the selected branch.'], 422);
        }

        if ($staffId) {
            $staff = User::find($staffId);
            if (!$staff || !$staff->isStaff() || $staff->branch_id !== $branchId) {
                return response()->json(['message' => 'Staff must belong to the selected branch.'], 422);
            }
        }

        return null;
    }

    /**
     * List available slots for a branch service.
     *
     * Endpoint: GET /api/branches/{branch}/services/{service}/slots
     * Auth: Public
     *
     * Returns active slots not already reserved by BOOKED or CHECKED_IN
     * appointments. Supports optional `date` filter and pagination.
     *
     * Responses:
     * - 200: Paginated available slot list
     *
     * @unauthenticated
     */
    public function available(Request $request, string $branchId, string $serviceId)
    {
        $query = Slot::where('branch_id', $branchId)
            ->where('service_type_id', $serviceId)
            ->where('is_active', true)
            ->whereDoesntHave('appointment', fn($q) => $q->whereIn('status', ['BOOKED', 'CHECKED_IN']));

        if ($request->filled('date')) {
            $date = Carbon::parse($request->date);
            $query->whereDate('start_at', $date);
        }

        $perPage = (int) $request->query('size', 15);
        $results = $query->paginate($perPage, ['*'], 'page', $request->query('page', 1));
        return response()->json(['data' => $results->items(), 'total' => $results->total()]);
    }

    /**
     * Create one or more appointment slots.
     *
     * Endpoint: POST /api/manage/slots
     * Auth: BRANCH_MANAGER, ADMIN
     *
     * Accepts either a single slot payload or a `slots` array for bulk creation.
     * Validates branch/service/staff relationships, enforces manager branch scope,
     * and records slot creation in the audit log.
     *
     * Responses:
     * - 201: Slot(s) created
     * - 403: Forbidden by branch scope rules
     * - 422: Validation or relationship constraints failed
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if ($request->has('slots')) {
            $request->validate([
                'slots' => 'required|array',
                'slots.*.branch_id' => 'required|exists:branches,id',
                'slots.*.service_type_id' => 'required|exists:service_types,id',
                'slots.*.staff_id' => 'nullable|exists:users,id',
                'slots.*.start_at' => 'required|date',
                'slots.*.end_at' => 'required|date',
                'slots.*.capacity' => 'nullable|integer|in:1',
            ]);
            $created = [];
            foreach ($request->slots as $slotData) {
                if ($forbidden = $this->assertManagerBranchScope($user, $slotData['branch_id'])) {
                    return $forbidden;
                }

                if ($invalidRelations = $this->assertSlotRelations(
                    $slotData['branch_id'],
                    $slotData['service_type_id'],
                    $slotData['staff_id'] ?? null
                )) {
                    return $invalidRelations;
                }

                $slotData['capacity'] = 1;
                $slot = Slot::create($slotData);
                AuditLog::log($user->id, $user->role, 'SLOT_CREATED', 'SLOT', $slot->id, null, $slot->branch_id);
                $created[] = $slot;
            }
            return response()->json(['data' => $created], 201);
        }

        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'service_type_id' => 'required|exists:service_types,id',
            'staff_id' => 'nullable|exists:users,id',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'capacity' => 'nullable|integer|in:1',
        ]);

        if ($forbidden = $this->assertManagerBranchScope($user, $validated['branch_id'])) {
            return $forbidden;
        }

        if ($invalidRelations = $this->assertSlotRelations(
            $validated['branch_id'],
            $validated['service_type_id'],
            $validated['staff_id'] ?? null
        )) {
            return $invalidRelations;
        }

        $validated['capacity'] = 1;

        $slot = Slot::create($validated);
        AuditLog::log($user->id, $user->role, 'SLOT_CREATED', 'SLOT', $slot->id, null, $slot->branch_id);

        return response()->json(['data' => $slot], 201);
    }

    /**
     * Update an existing slot.
     *
     * Endpoint: PUT /api/manage/slots/{id}
     * Auth: BRANCH_MANAGER, ADMIN
     *
     * Applies partial updates, validates cross-entity consistency, and enforces
     * manager branch scope restrictions.
     *
     * Responses:
     * - 200: Slot updated
     * - 403: Forbidden by branch scope rules
     * - 404: Slot not found
     * - 422: Validation or relationship constraints failed
     */
    public function update(Request $request, string $id)
    {
        $user = $request->user();
        $slot = Slot::findOrFail($id);

        if ($forbidden = $this->assertManagerBranchScope($user, $slot->branch_id)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'branch_id' => 'sometimes|exists:branches,id',
            'service_type_id' => 'sometimes|exists:service_types,id',
            'staff_id' => 'sometimes|nullable|exists:users,id',
            'start_at' => 'sometimes|date',
            'end_at' => 'sometimes|date',
            'capacity' => 'sometimes|integer|in:1',
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($validated['branch_id']) && ($forbidden = $this->assertManagerBranchScope($user, $validated['branch_id']))) {
            return $forbidden;
        }

        $targetBranchId = $validated['branch_id'] ?? $slot->branch_id;
        $targetServiceTypeId = $validated['service_type_id'] ?? $slot->service_type_id;
        $targetStaffId = array_key_exists('staff_id', $validated) ? $validated['staff_id'] : $slot->staff_id;

        if ($invalidRelations = $this->assertSlotRelations($targetBranchId, $targetServiceTypeId, $targetStaffId)) {
            return $invalidRelations;
        }

        if (isset($validated['capacity'])) {
            $validated['capacity'] = 1;
        }

        $slot->update($validated);
        AuditLog::log($user->id, $user->role, 'SLOT_UPDATED', 'SLOT', $slot->id, null, $slot->branch_id);

        return response()->json(['data' => $slot]);
    }

    /**
     * Soft-delete a slot.
     *
     * Endpoint: DELETE /api/manage/slots/{id}
     * Auth: BRANCH_MANAGER, ADMIN
     *
     * Enforces manager branch scope and records deletion in the audit log.
     *
     * Responses:
     * - 200: Slot soft-deleted
     * - 403: Forbidden by branch scope rules
     * - 404: Slot not found
     */
    public function destroy(Request $request, string $id)
    {
        $user = $request->user();
        $slot = Slot::findOrFail($id);

        if ($forbidden = $this->assertManagerBranchScope($user, $slot->branch_id)) {
            return $forbidden;
        }

        $slot->delete();
        AuditLog::log($user->id, $user->role, 'SLOT_DELETED', 'SLOT', $slot->id, null, $slot->branch_id);
        return response()->json(['message' => 'Slot soft-deleted.']);
    }

    /**
     * List soft-deleted slots.
     *
     * Endpoint: GET /api/admin/slots/trashed
     * Auth: ADMIN
     *
     * Returns only soft-deleted slots with pagination.
     *
     * Responses:
     * - 200: Paginated trashed slot list
     */
    public function trashed(Request $request)
    {
        $perPage = (int) $request->query('size', 15);
        $page = (int) $request->query('page', 1);

        $results = Slot::onlyTrashed()
            ->with(['branch', 'serviceType', 'staff'])
            ->orderBy('deleted_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json(['data' => $results->items(), 'total' => $results->total()]);
    }

    /**
     * Permanently remove soft-deleted slots after retention period.
     *
     * Endpoint: POST /api/admin/slots/cleanup
     * Auth: ADMIN
     *
     * Hard-deletes slots past configured retention and detaches related
     * appointments by setting `slot_id` to null.
     *
     * Responses:
     * - 200: Cleanup summary returned
     */
    public function cleanup(Request $request)
    {
        $user = $request->user();
        $retentionDays = (int) Setting::get('soft_delete_retention_days', 30);
        $cutoff = now()->subDays($retentionDays);

        $slots = Slot::onlyTrashed()->where('deleted_at', '<=', $cutoff)->get();

        foreach ($slots as $slot) {
            Appointment::where('slot_id', $slot->id)->update(['slot_id' => null]);
            AuditLog::log($user->id, $user->role, 'SLOT_HARD_DELETED', 'SLOT', $slot->id, ['reason' => 'retention_cleanup'], $slot->branch_id);
            $slot->forceDelete();
        }

        return response()->json(['message' => "Hard-deleted {$slots->count()} slots."]);
    }
}

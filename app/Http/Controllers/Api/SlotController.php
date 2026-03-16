<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ServiceType;
use App\Models\Slot;
use App\Models\User;
use Dedoc\Scramble\Attributes\QueryParameter;
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

    #[QueryParameter('date', description: 'Filter slots by date (YYYY-MM-DD).', type: 'string', required: false, example: '2026-03-20')]
    #[QueryParameter('term', description: 'Search slot id or assigned staff name/email (case-insensitive).', type: 'string', required: false, example: 'fatima')]
    #[QueryParameter('page', description: 'Page number.', type: 'integer', required: false, example: 1)]
    #[QueryParameter('size', description: 'Number of records per page.', type: 'integer', required: false, example: 15)]
    /**
     * List available slots for a branch service.
     *
     * Endpoint: GET /api/branches/{branch}/services/{service}/slots
     * Auth: Public
     *
     * Returns active slots not already reserved by BOOKED or CHECKED_IN
    * appointments. Supports optional `date` filter, optional case-insensitive
    * `term` search, and pagination.
    *
    * Response shape:
    * - `results`: records for the current page
    * - `total`: total matching records
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

        if ($request->filled('term')) {
            $term = '%' . $request->term . '%';
            $query->where(function ($q) use ($term) {
                $q->where('id', 'ilike', $term)
                    ->orWhereHas('staff', fn ($s) => $s->where('full_name', 'ilike', $term)->orWhere('email', 'ilike', $term));
            });
        }

        $perPage = (int) $request->query('size', 15);
        $results = $query->paginate($perPage, ['*'], 'page', $request->query('page', 1));
        return response()->json(['results' => $results->items(), 'total' => $results->total()]);
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

    #[QueryParameter('term', description: 'Search by slot id, branch, service, or staff (case-insensitive).', type: 'string', required: false, example: 'muscat')]
    #[QueryParameter('page', description: 'Page number.', type: 'integer', required: false, example: 1)]
    #[QueryParameter('size', description: 'Number of records per page.', type: 'integer', required: false, example: 15)]
    /**
     * List soft-deleted slots.
     *
     * Endpoint: GET /api/admin/slots/trashed
     * Auth: ADMIN
     *
    * Returns only soft-deleted slots with optional case-insensitive `term`
    * search and pagination.
    *
    * Response shape:
    * - `results`: records for the current page
    * - `total`: total matching records
     *
     * Responses:
     * - 200: Paginated trashed slot list
     */
    public function trashed(Request $request)
    {
        $query = Slot::onlyTrashed()
            ->with(['branch', 'serviceType', 'staff']);

        if ($request->filled('term')) {
            $term = '%' . $request->term . '%';
            $query->where(function ($q) use ($term) {
                $q->where('id', 'ilike', $term)
                    ->orWhereHas('branch', fn ($b) => $b->where('name', 'ilike', $term)->orWhere('city', 'ilike', $term))
                    ->orWhereHas('serviceType', fn ($s) => $s->where('name', 'ilike', $term))
                    ->orWhereHas('staff', fn ($s) => $s->where('full_name', 'ilike', $term)->orWhere('email', 'ilike', $term));
            });
        }

        $perPage = (int) $request->query('size', 15);
        $page = (int) $request->query('page', 1);

        $results = $query
            ->orderBy('deleted_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json(['results' => $results->items(), 'total' => $results->total()]);
    }

}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\StaffServiceType;
use App\Models\User;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = User::whereIn('role', ['STAFF', 'BRANCH_MANAGER']);

        if ($user->isBranchManager()) {
            $query->where('branch_id', $user->branch_id);
        }

        if ($request->filled('term')) {
            $term = '%' . $request->term . '%';
            $query->where(function ($q) use ($term) {
                $q->where('full_name', 'ilike', $term)->orWhere('email', 'ilike', $term);
            });
        }

        $perPage = (int) $request->query('size', 15);
        $results = $query->with('staffServiceTypes')->paginate($perPage, ['*'], 'page', $request->query('page', 1));
        return response()->json(['data' => $results->items(), 'total' => $results->total()]);
    }

    public function assign(Request $request, string $id)
    {
        $user = $request->user();
        $staff = User::findOrFail($id);

        $validated = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'service_type_ids' => 'nullable|array',
            'service_type_ids.*' => 'exists:service_types,id',
        ]);

        if (isset($validated['branch_id'])) {
            $staff->update(['branch_id' => $validated['branch_id']]);
        }

        if (isset($validated['service_type_ids'])) {
            StaffServiceType::where('staff_id', $staff->id)->delete();
            foreach ($validated['service_type_ids'] as $svcId) {
                StaffServiceType::firstOrCreate(['staff_id' => $staff->id, 'service_type_id' => $svcId]);
            }
        }

        AuditLog::log($user->id, $user->role, 'STAFF_ASSIGNED', 'USER', $staff->id, $validated, $staff->branch_id);
        return response()->json(['data' => $staff->fresh()->load('staffServiceTypes')]);
    }
}

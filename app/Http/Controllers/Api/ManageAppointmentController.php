<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Appointment;
use Illuminate\Http\Request;

class ManageAppointmentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Appointment::with(['customer', 'slot', 'serviceType', 'branch', 'staff']);

        if ($user->isBranchManager()) {
            $query->where('branch_id', $user->branch_id);
        } elseif ($user->isStaff()) {
            $query->where('staff_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = (int) $request->query('size', 15);
        $results = $query->paginate($perPage, ['*'], 'page', $request->query('page', 1));
        return response()->json(['data' => $results->items(), 'total' => $results->total()]);
    }

    public function updateStatus(Request $request, string $id)
    {
        $user = $request->user();
        $validated = $request->validate([
            'status' => 'required|in:CHECKED_IN,COMPLETED,NO_SHOW',
            'notes' => 'nullable|string',
        ]);

        $appointment = Appointment::findOrFail($id);

        if ($user->isBranchManager() && $appointment->branch_id !== $user->branch_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($user->isStaff() && $appointment->staff_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $appointment->update($validated);

        AuditLog::log($user->id, $user->role, 'APPOINTMENT_STATUS_UPDATED', 'APPOINTMENT', $appointment->id, ['status' => $validated['status']], $appointment->branch_id);

        return response()->json(['data' => $appointment->fresh()]);
    }
}

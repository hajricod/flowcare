<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Appointment;
use App\Models\Setting;
use App\Models\Slot;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SlotController extends Controller
{
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
                'slots.*.capacity' => 'nullable|integer|min:1',
            ]);
            $created = [];
            foreach ($request->slots as $slotData) {
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
            'capacity' => 'nullable|integer|min:1',
        ]);

        $slot = Slot::create($validated);
        AuditLog::log($user->id, $user->role, 'SLOT_CREATED', 'SLOT', $slot->id, null, $slot->branch_id);

        return response()->json(['data' => $slot], 201);
    }

    public function update(Request $request, string $id)
    {
        $user = $request->user();
        $slot = Slot::findOrFail($id);

        $validated = $request->validate([
            'branch_id' => 'sometimes|exists:branches,id',
            'service_type_id' => 'sometimes|exists:service_types,id',
            'staff_id' => 'sometimes|nullable|exists:users,id',
            'start_at' => 'sometimes|date',
            'end_at' => 'sometimes|date',
            'capacity' => 'sometimes|integer|min:1',
            'is_active' => 'sometimes|boolean',
        ]);

        $slot->update($validated);
        AuditLog::log($user->id, $user->role, 'SLOT_UPDATED', 'SLOT', $slot->id, null, $slot->branch_id);

        return response()->json(['data' => $slot]);
    }

    public function destroy(Request $request, string $id)
    {
        $user = $request->user();
        $slot = Slot::findOrFail($id);
        $slot->delete();
        AuditLog::log($user->id, $user->role, 'SLOT_DELETED', 'SLOT', $slot->id, null, $slot->branch_id);
        return response()->json(['message' => 'Slot soft-deleted.']);
    }

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

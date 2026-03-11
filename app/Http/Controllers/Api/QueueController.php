<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Branch;

class QueueController extends Controller
{
    /**
     * @unauthenticated
     */
    public function liveQueue(string $branchId)
    {
        $branch = Branch::findOrFail($branchId);
        $count = Appointment::where('branch_id', $branch->id)
            ->whereIn('status', ['BOOKED', 'CHECKED_IN'])
            ->whereDate('created_at', today())
            ->count();
        return response()->json(['branch_id' => $branch->id, 'active_queue_count' => $count]);
    }
}

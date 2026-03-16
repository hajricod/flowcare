<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Branch;

class QueueController extends Controller
{
    /**
    * Get today's live queue number for a branch.
    *
    * Endpoint: GET /api/branches/{branch}/queue
    * Auth: Public
    *
    * Computes a real-time count of active appointments (BOOKED or CHECKED_IN)
    * created today for the selected branch.
    *
    * Responses:
    * - 200: Branch queue count returned
    * - 404: Branch not found
    *
     * @unauthenticated
     */
    public function liveQueue(string $branchId)
    {
        $branch = Branch::findOrFail($branchId);
        $liveQueueNumber = Appointment::where('branch_id', $branch->id)
            ->whereIn('status', ['BOOKED', 'CHECKED_IN'])
            ->whereDate('created_at', today())
            ->count();

        return response()->json([
            'branch_id' => $branch->id,
            'live_queue_number' => $liveQueueNumber,
            'active_queue_count' => $liveQueueNumber,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Branch;

class QueueController extends Controller
{
    /**
    * Get today's active queue count for a branch.
    *
    * Endpoint: GET /api/branches/{branch}/queue
    * Auth: Public
    *
    * Counts appointments in BOOKED or CHECKED_IN status created today.
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
        $count = Appointment::where('branch_id', $branch->id)
            ->whereIn('status', ['BOOKED', 'CHECKED_IN'])
            ->whereDate('created_at', today())
            ->count();
        return response()->json(['branch_id' => $branch->id, 'active_queue_count' => $count]);
    }
}

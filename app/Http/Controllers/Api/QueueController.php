<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Branch;
use Dedoc\Scramble\Attributes\QueryParameter;

class QueueController extends Controller
{
    private const ACTIVE_QUEUE_STATUSES = ['BOOKED', 'CHECKED_IN'];

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
        $liveQueueNumber = $this->countTodayActiveQueue($branch->id);

        return response()->json([
            'branch_id' => $branch->id,
            'live_queue_number' => $liveQueueNumber,
            'active_queue_count' => $liveQueueNumber,
        ]);
    }

    #[QueryParameter('interval', description: 'Seconds between push updates (1 to 10).', type: 'integer', required: false, example: 2)]
    #[QueryParameter('duration', description: 'Maximum stream duration in seconds (10 to 300).', type: 'integer', required: false, example: 60)]
    /**
    * Stream today's live queue number for a branch using SSE.
    *
    * Endpoint: GET /api/branches/{branch}/queue/stream
    * Auth: Public
    *
    * Pushes periodic `queue.update` events and a final `queue.end` event when
    * the stream duration is reached.
    *
    * Responses:
    * - 200: Event stream started
    * - 404: Branch not found
    *
     * @unauthenticated
     */
    public function streamQueue(string $branchId)
    {
        $branch = Branch::findOrFail($branchId);

        $intervalSeconds = max(1, min(10, (int) request()->query('interval', 2)));
        $durationSeconds = max(10, min(300, (int) request()->query('duration', 60)));

        return response()->stream(function () use ($branch, $intervalSeconds, $durationSeconds) {
            $startedAt = microtime(true);

            // Long-lived SSE connection for queue updates.
            while (! connection_aborted()) {
                $now = now();
                $liveQueueNumber = $this->countTodayActiveQueue($branch->id);

                $payload = [
                    'branch_id' => $branch->id,
                    'live_queue_number' => $liveQueueNumber,
                    'active_queue_count' => $liveQueueNumber,
                    'timestamp' => $now->toIso8601String(),
                ];

                echo "event: queue.update\n";
                echo 'id: ' . $now->timestamp . "\n";
                echo 'data: ' . json_encode($payload) . "\n\n";

                @ob_flush();
                @flush();

                if ((microtime(true) - $startedAt) >= $durationSeconds) {
                    echo "event: queue.end\n";
                    echo 'data: ' . json_encode(['reason' => 'duration_reached']) . "\n\n";
                    @ob_flush();
                    @flush();
                    break;
                }

                sleep($intervalSeconds);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function countTodayActiveQueue(string $branchId): int
    {
        return Appointment::where('branch_id', $branchId)
            ->whereIn('status', self::ACTIVE_QUEUE_STATUSES)
            ->whereDate('created_at', today())
            ->count();
    }
}

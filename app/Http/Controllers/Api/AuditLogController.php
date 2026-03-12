<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * List audit logs.
     *
     * Endpoints:
     * - GET /api/manage/audit-logs (STAFF, BRANCH_MANAGER, ADMIN)
     *
     * Branch managers are limited to their branch logs. Supports pagination.
     *
     * Responses:
     * - 200: Paginated audit log list
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = AuditLog::query();

        if ($user->isBranchManager()) {
            $query->where('branch_id', $user->branch_id);
        }

        $perPage = (int) $request->query('size', 15);
        $page = (int) $request->query('page', 1);
        $results = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
        return response()->json(['data' => $results->items(), 'total' => $results->total()]);
    }

    /**
     * Export audit logs as CSV.
     *
     * Endpoint: GET /api/admin/audit-logs/export
     * Auth: ADMIN
     *
     * Streams logs in descending creation order as a downloadable CSV file.
     * Branch managers remain scoped to their own branch if invoked in that role.
     *
     * Responses:
     * - 200: CSV stream download
     */
    public function export(Request $request)
    {
        $user = $request->user();
        $query = AuditLog::query();

        if ($user->isBranchManager()) {
            $query->where('branch_id', $user->branch_id);
        }

        $logs = $query->orderBy('created_at', 'desc')->get();

        $headers = ['id', 'actor_id', 'actor_role', 'action_type', 'entity_type', 'entity_id', 'metadata', 'branch_id', 'created_at'];

        $callback = function () use ($logs, $headers) {
            $fp = fopen('php://output', 'w');
            fputcsv($fp, $headers);
            foreach ($logs as $log) {
                fputcsv($fp, [
                    $log->id,
                    $log->actor_id,
                    $log->actor_role,
                    $log->action_type,
                    $log->entity_type,
                    $log->entity_id,
                    json_encode($log->metadata),
                    $log->branch_id,
                    $log->created_at,
                ]);
            }
            fclose($fp);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="audit_logs_' . now()->format('Y-m-d') . '.csv"',
        ]);
    }
}

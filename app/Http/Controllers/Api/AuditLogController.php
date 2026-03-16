<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    #[QueryParameter('term', description: 'Search by action, entity, actor, role, or branch (case-insensitive).', type: 'string', required: false, example: 'appointment')]
    #[QueryParameter('page', description: 'Page number.', type: 'integer', required: false, example: 1)]
    #[QueryParameter('size', description: 'Number of records per page.', type: 'integer', required: false, example: 15)]
    /**
     * List audit logs for staff operations.
     *
     * Endpoint: GET /api/manage/audit-logs
     * Auth: STAFF, BRANCH_MANAGER, ADMIN
     *
    * Staff and branch managers are limited to their branch logs. Supports
    * pagination and optional case-insensitive `term` search.
    *
    * Response shape:
    * - `results`: records for the current page
    * - `total`: total matching records
     *
     * Responses:
     * - 200: Paginated audit log list
     */
    public function manageIndex(Request $request)
    {
        return $this->paginateLogs($request);
    }

    #[QueryParameter('term', description: 'Search by action, entity, actor, role, or branch (case-insensitive).', type: 'string', required: false, example: 'appointment')]
    #[QueryParameter('page', description: 'Page number.', type: 'integer', required: false, example: 1)]
    #[QueryParameter('size', description: 'Number of records per page.', type: 'integer', required: false, example: 15)]
    /**
     * List all audit logs for administrators.
     *
     * Endpoint: GET /api/admin/audit-logs
     * Auth: ADMIN
     *
    * Supports pagination and optional case-insensitive `term` search.
    *
    * Response shape:
    * - `results`: records for the current page
    * - `total`: total matching records
    *
     * Responses:
     * - 200: Paginated audit log list
     */
    public function adminIndex(Request $request)
    {
        return $this->paginateLogs($request);
    }

    /**
     * Export audit logs as CSV.
     *
     * Endpoint: GET /api/admin/audit-logs/export
     * Auth: ADMIN
     *
     * Streams logs in descending creation order as a downloadable CSV file.
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

    private function paginateLogs(Request $request)
    {
        $user = $request->user();
        $query = AuditLog::query();

        if ($user->isStaff() || $user->isBranchManager()) {
            if (! $user->branch_id) {
                return response()->json(['results' => [], 'total' => 0]);
            }

            $query->where('branch_id', $user->branch_id);
        }

        if ($request->filled('term')) {
            $term = '%' . $request->term . '%';
            $query->where(function ($q) use ($term) {
                $q->where('action_type', 'ilike', $term)
                    ->orWhere('entity_type', 'ilike', $term)
                    ->orWhere('entity_id', 'ilike', $term)
                    ->orWhere('actor_id', 'ilike', $term)
                    ->orWhere('actor_role', 'ilike', $term)
                    ->orWhere('branch_id', 'ilike', $term);
            });
        }

        $perPage = (int) $request->query('size', 15);
        $page = (int) $request->query('page', 1);
        $results = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json(['results' => $results->items(), 'total' => $results->total()]);
    }
}

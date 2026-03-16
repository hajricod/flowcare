<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ServiceType;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;

class ServiceTypeController extends Controller
{
    #[QueryParameter('term', description: 'Search service name or description (case-insensitive).', type: 'string', required: false, example: 'consult')]
    #[QueryParameter('page', description: 'Page number.', type: 'integer', required: false, example: 1)]
    #[QueryParameter('size', description: 'Number of records per page.', type: 'integer', required: false, example: 15)]
    /**
    * List active service types for a specific active branch.
    *
    * Endpoint: GET /api/branches/{branch}/services
    * Auth: Public
    *
    * Supports pagination with `page` and `size`, plus optional case-insensitive
    * `term` search across service name and description.
    *
    * Response shape:
    * - `results`: records for the current page
    * - `total`: total matching records
    *
    * Responses:
    * - 200: Paginated service type list
    * - 404: Branch not found or inactive
    *
     * @unauthenticated
     */
    public function byBranch(Request $request, string $branchId)
    {
        $branch = Branch::where('is_active', true)->findOrFail($branchId);
        $query = ServiceType::where('branch_id', $branch->id)->where('is_active', true);

        if ($request->filled('term')) {
            $term = '%' . $request->term . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'ilike', $term)
                    ->orWhere('description', 'ilike', $term);
            });
        }

        $perPage = (int) $request->query('size', 15);
        $page = (int) $request->query('page', 1);
        $results = $query->paginate($perPage, ['*'], 'page', $page);
        return response()->json(['results' => $results->items(), 'total' => $results->total()]);
    }
}

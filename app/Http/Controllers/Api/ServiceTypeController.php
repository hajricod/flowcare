<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ServiceType;
use Illuminate\Http\Request;

class ServiceTypeController extends Controller
{
    /**
    * List active service types for a specific active branch.
    *
    * Endpoint: GET /api/branches/{branch}/services
    * Auth: Public
    *
    * Supports pagination with `page` and `size` query parameters.
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
        $perPage = (int) $request->query('size', 15);
        $page = (int) $request->query('page', 1);
        $results = $query->paginate($perPage, ['*'], 'page', $page);
        return response()->json(['data' => $results->items(), 'total' => $results->total()]);
    }
}

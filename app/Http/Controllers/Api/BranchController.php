<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    #[QueryParameter('term', description: 'Search branch name or city (case-insensitive).', type: 'string', required: false, example: 'muscat')]
    #[QueryParameter('page', description: 'Page number.', type: 'integer', required: false, example: 1)]
    #[QueryParameter('size', description: 'Number of records per page.', type: 'integer', required: false, example: 15)]
    /**
    * List active branches with optional search.
    *
    * Endpoint: GET /api/branches
    * Auth: Public
    *
    * Supports pagination and optional `term` filtering by branch name or city.
    *
    * Response shape:
    * - `results`: records for the current page
    * - `total`: total matching records
    *
    * Responses:
    * - 200: Paginated list of active branches
    *
    * @unauthenticated
     */
    public function index(Request $request)
    {
        $query = Branch::where('is_active', true);
        if ($request->filled('term')) {
            $term = '%' . $request->term . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'ilike', $term)->orWhere('city', 'ilike', $term);
            });
        }
        $perPage = (int) $request->query('size', 15);
        $results = $query->paginate($perPage, ['*'], 'page', $request->query('page', 1));
        return response()->json(['results' => $results->items(), 'total' => $results->total()]);
    }
}

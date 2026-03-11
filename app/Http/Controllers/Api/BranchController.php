<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    #[QueryParameter('term', description: 'Search branch name or city (case-insensitive).', type: 'string', required: false, example: 'muscat')]
    /**
    * List active branches with optional search.
    *
    * Endpoint: GET /api/branches
    * Auth: Public
    *
    * Supports pagination and optional `term` filtering by branch name or city.
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
        return response()->json(['data' => $results->items(), 'total' => $results->total()]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ServiceType;
use Illuminate\Http\Request;

class ServiceTypeController extends Controller
{
    /**
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

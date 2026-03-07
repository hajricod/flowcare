<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\ServiceType;
use Illuminate\Http\Request;

class ServiceTypeController extends Controller
{
    public function byBranch(Request $request, string $branchId)
    {
        $branch = Branch::where('is_active', true)->findOrFail($branchId);
        $query = ServiceType::where('branch_id', $branch->id)->where('is_active', true);
        $perPage = (int) $request->get('size', 15);
        $results = $query->paginate($perPage, ['*'], 'page', $request->get('page', 1));
        return response()->json(['data' => $results->items(), 'total' => $results->total()]);
    }
}

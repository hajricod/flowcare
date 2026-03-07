<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;

class BranchController extends Controller
{
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

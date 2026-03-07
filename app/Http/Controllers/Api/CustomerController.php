<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = User::where('role', 'CUSTOMER');

        if ($request->filled('term')) {
            $term = '%' . $request->term . '%';
            $query->where(function ($q) use ($term) {
                $q->where('full_name', 'ilike', $term)
                  ->orWhere('email', 'ilike', $term)
                  ->orWhere('username', 'ilike', $term);
            });
        }

        $perPage = (int) $request->query('size', 15);
        $results = $query->paginate($perPage, ['*'], 'page', $request->query('page', 1));
        return response()->json(['data' => $results->items(), 'total' => $results->total()]);
    }

    public function show(string $id)
    {
        $customer = User::where('role', 'CUSTOMER')->findOrFail($id);
        return response()->json(['data' => $customer]);
    }

    public function getIdImage(string $id)
    {
        $customer = User::where('role', 'CUSTOMER')->findOrFail($id);

        if (!$customer->id_image_path || !Storage::disk('local')->exists($customer->id_image_path)) {
            return response()->json(['message' => 'ID image not found.'], 404);
        }

        return Storage::disk('local')->download($customer->id_image_path);
    }
}

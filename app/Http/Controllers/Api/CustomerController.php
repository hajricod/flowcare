<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CustomerController extends Controller
{
    /**
     * List customers for management.
     *
     * Endpoint: GET /api/manage/customers
     * Auth: BRANCH_MANAGER, ADMIN
     *
     * Supports optional `term` search across name, email, and username, with
     * pagination.
     *
     * Responses:
     * - 200: Paginated customer list
     */
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

    /**
     * Get a single customer profile.
     *
     * Endpoint: GET /api/manage/customers/{id}
     * Auth: BRANCH_MANAGER, ADMIN
     *
     * Responses:
     * - 200: Customer found
     * - 404: Customer not found
     */
    public function show(string $id)
    {
        $customer = User::where('role', 'CUSTOMER')->findOrFail($id);
        return response()->json(['data' => $customer]);
    }

    /**
     * Download customer ID image.
     *
     * Endpoint: GET /api/admin/customers/{id}/id-image
     * Auth: ADMIN
     *
     * Responses:
     * - 200: File download
     * - 404: Customer or ID image not found
     */
    public function getIdImage(string $id)
    {
        $customer = User::where('role', 'CUSTOMER')->findOrFail($id);

        if (!$customer->id_image_path || !Storage::disk('local')->exists($customer->id_image_path)) {
            return response()->json(['message' => 'ID image not found.'], 404);
        }

        return Storage::disk('local')->download($customer->id_image_path);
    }
}

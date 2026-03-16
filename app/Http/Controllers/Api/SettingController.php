<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Update soft-delete retention policy in days.
     *
     * Endpoint: PUT /api/admin/settings/retention
     * Auth: ADMIN
     *
     * Request body:
     * - days (required, integer >= 1)
     *
     * Responses:
     * - 200: Retention setting updated
     * - 422: Validation failed
     */
    public function updateRetention(Request $request)
    {
        $validated = $request->validate(['days' => 'required|integer|min:1']);
        Setting::set('soft_delete_retention_days', (string) $validated['days']);
        return response()->json(['data' => ['soft_delete_retention_days' => $validated['days']]]);
    }
}

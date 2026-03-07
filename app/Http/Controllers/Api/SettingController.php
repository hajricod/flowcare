<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function updateRetention(Request $request)
    {
        $validated = $request->validate(['days' => 'required|integer|min:1']);
        Setting::set('soft_delete_retention_days', (string) $validated['days']);
        return response()->json(['data' => ['soft_delete_retention_days' => $validated['days']]]);
    }
}

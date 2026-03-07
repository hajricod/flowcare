<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'full_name' => 'required|string',
            'phone' => 'nullable|string',
            'id_image' => 'nullable|image|max:5120',
        ]);

        $idImagePath = null;
        if ($request->hasFile('id_image')) {
            $ext = $request->file('id_image')->getClientOriginalExtension();
            $uuid = Str::uuid();
            $idImagePath = "uploads/customers/{$uuid}.{$ext}";
            Storage::disk('local')->putFileAs('uploads/customers', $request->file('id_image'), "{$uuid}.{$ext}");
        }

        $user = User::create([
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'full_name' => $validated['full_name'],
            'phone' => $validated['phone'] ?? null,
            'role' => 'CUSTOMER',
            'id_image_path' => $idImagePath,
            'is_active' => true,
        ]);

        AuditLog::log($user->id, 'CUSTOMER', 'CUSTOMER_REGISTERED', 'USER', $user->id);

        return response()->json(['data' => $user], 201);
    }

    public function login(Request $request)
    {
        $user = $request->user();
        return response()->json(['data' => $user]);
    }

    public function me(Request $request)
    {
        return response()->json(['data' => $request->user()]);
    }
}

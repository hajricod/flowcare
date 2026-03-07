<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class BasicAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Basic ')) {
            return response()->json(
                ['message' => 'Unauthorized.'],
                401,
                ['WWW-Authenticate' => 'Basic realm="FlowCare"']
            );
        }

        $credentials = base64_decode(substr($authHeader, 6));
        if ($credentials === false) {
            return response()->json(
                ['message' => 'Invalid credentials.'],
                401,
                ['WWW-Authenticate' => 'Basic realm="FlowCare"']
            );
        }
        [$username, $password] = array_pad(explode(':', $credentials, 2), 2, '');

        if (empty($username) || empty($password)) {
            return response()->json(
                ['message' => 'Invalid credentials.'],
                401,
                ['WWW-Authenticate' => 'Basic realm="FlowCare"']
            );
        }

        $user = User::where('username', $username)->where('is_active', true)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return response()->json(
                ['message' => 'Invalid credentials.'],
                401,
                ['WWW-Authenticate' => 'Basic realm="FlowCare"']
            );
        }

        auth()->login($user);
        $request->setUserResolver(fn() => $user);

        return $next($request);
    }
}

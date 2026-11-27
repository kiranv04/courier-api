<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if(Auth::attempt($credentials)){
            if ($request->hasSession()) {
                $request->session()->regenerate();
            }

            $user = Auth::user();

            if (! $user->is_active) {
                Auth::logout();
                return response()->json(['message' => 'Your account has been deactivated. Please contact your administrator.'], 403);
            }

            return response()->json([
                'message' => 'Login Successful!',
                'user' => $user,
                'must_change_password' => $user->must_change_password ?? false,
            ], 200);
        }

        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    /**
     * Token login for the standalone driver app (separate domain, so no session cookies).
     * Only branch-delivery users may obtain a token here.
     */
    public function driverLogin(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:100',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Your account has been deactivated. Please contact your administrator.'], 403);
        }

        if (! $user->hasRole('branch-delivery')) {
            return response()->json(['message' => 'This app is for delivery agents only.'], 403);
        }

        $token = $user->createToken($credentials['device_name'] ?? 'driver-app', ['driver'])->plainTextToken;

        return response()->json([
            'message' => 'Login Successful!',
            'token' => $token,
            'user' => $user->load('roles'),
            'must_change_password' => $user->must_change_password ?? false,
        ]);
    }

    public function me(Request $request) : JsonResponse
    {
        return response()->json($request->user()->load('roles'));
    }

    public function logout(Request $request) : JsonResponse 
    {
        // Bearer-token clients (driver app): revoke the current token. Cookie auth uses a TransientToken.
        $token = $request->user()?->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
            return response()->json(['message' => 'Logged out'], 200);
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Logged out'], 200);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|confirmed|min:8|max:255|different:current_password',
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
        ]);

        return response()->json(['message' => 'Password changed successfully']);
    }
}

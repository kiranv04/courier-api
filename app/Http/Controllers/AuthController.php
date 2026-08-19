<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if(Auth::attempt($credentials)){
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

    public function me(Request $request) : JsonResponse
    {
        return response()->json($request->user()->load('roles'));
    }

    public function logout(Request $request) : JsonResponse 
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out'], 200);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|confirmed|min:8',
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

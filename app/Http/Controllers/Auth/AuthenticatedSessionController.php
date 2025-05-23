<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request) : JsonResponse
        // Validate the request
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'Location' => 'required|in:Dhaka,Rajsahi,Khulna',
        ]);

        $connection = strtolower($request->Location);



        $user = User::on($connection)->where('email', $request->email)->first();


        if (! $user || ! Hash::check($request->password, $user->password)) {
        return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // This now works correctly after setting $primaryKey
        $user = User::on($connection)->find($user->user_id);

        if (!$user) {
        return response()->json(['message' => 'User not found for token creation'], 500);
        }

        Auth::login($user);

        $token = $user->createToken('edunexus')->plainTextToken;

        return response()->json([
            'message' => 'User successfully logged in',
            'token' => $token,
            'user' => $user,
        ]);

    }

    public function locations($email): JsonResponse
    {
        try {
            $user = User::where('email', $email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'location' => $user->Location
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving location'
            ], 500);
        }
    }




    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete(); // delete the token from database
            return response()->json(['message' => 'Logged out successfully']);
        }

        return response()->json(['message' => 'Unauthenticated'], 401);
    }
}

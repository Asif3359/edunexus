<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Log;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */

    public function store(Request $request): \Illuminate\Http\JsonResponse
        {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
                'password' => ['required', Rules\Password::defaults()],
                'role' => 'required|in:student,teacher,admin',
                'Location' => 'required|in:Dhaka,Rajsahi,Khulna',
            ]);

            // Select connection based on Location
            $connection =  strtolower($request->Location);

            // Insert into selected database
            $user = (new User)->setConnection($connection)->create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'Location' => $request->Location,
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'Location' => $request->Location,
            ]);


            event(new Registered($user));

            Auth::login($user);

            return response()->json([
                'message' => 'User successfully registered',
                'user' => $user,
                'token' => $user->createToken('edunexus')->plainTextToken,
            ]);
        }

}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Experience;
use App\Models\Subscription;
use App\Models\UserExperiences;
use Illuminate\Support\Facades\Config;

class PaymentController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function createPaymentIntent(Request $request)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:1',
                'currency' => 'required|string|size:3',
            ]);

            $paymentIntent = PaymentIntent::create([
                'amount' => $request->amount,
                'currency' => $request->currency,
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
                'metadata' => [
                    'integration_check' => 'accept_a_payment',
                ],
            ]);

            return response()->json([
                'clientSecret' => $paymentIntent->client_secret,
                'paymentIntentId' => $paymentIntent->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Payment intent creation failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create payment intent',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function applyForTeacher(Request $request)
    {
        try {
            $request->validate([
                'userId' => 'required|exists:users,user_id',
                'experiences' => 'required|array',
                'experiences.*.organization' => 'required|string',
                'experiences.*.role' => 'required|string',
                'experiences.*.duration' => 'required|string',
                'experiences.*.description' => 'nullable|string',
                'location' => 'required|string|in:Dhaka,Rajsahi,Khulna',
                'paymentIntentId' => 'required|string',
            ]);

            $connection = strtolower($request->location);

            // Switch DB connection
            Config::set('database.default', $connection);
            DB::purge($connection);
            DB::reconnect($connection);

            $user = User::where('user_id', $request->userId)->first();

            // Log::debug($user);

            if (!$user) {
                throw new \Exception('User not found');
            }

            // Verify payment first
            $paymentIntentId = explode('_secret_', $request->paymentIntentId)[0];
            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);
            // Log::debug($paymentIntentId);
            // Log::debug($paymentIntent);

            if ($paymentIntent->status !== 'succeeded') {
                throw new \Exception('Payment not completed');
            }

            // Update user role
            $user->role = 'teacher';
            $user->save();

            // now insert into experiences table
            foreach ($request->experiences as $experienceData) {
                $experience = Experience::create([
                    // 'user_id' => $user->user_id,
                    'organization' => $experienceData['organization'],
                    'role' => $experienceData['role'],
                    'duration' => $experienceData['duration'],
                    'description' => $experienceData['description'] ?? null,
                ]);

                // Attach experience to user using the many-to-many relationship
                $user->experiences()->attach($experience->id);
            }

            // now insert into subscriptions table
            Subscription::create([
                'teacher_id' => $user->user_id,
                'plan_name' => 'Basic Plan',
                'stripe_payment_id' => $paymentIntentId,
                'price' => $paymentIntent->amount / 100,
                'start_date' => now(),
                'end_date' => now()->addDays(60),
                ]);

                Log::debug("experiences inserted " . $user->user_id);

            return response()->json([
                'message' => 'Teacher application submitted successfully',
                'user' => $user,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Teacher application failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to submit teacher application',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function verifyPayment(Request $request)
    {
        try {
            $request->validate([
                'paymentIntentId' => 'required|string',
            ]);

            $paymentIntent = PaymentIntent::retrieve($request->paymentIntentId);

            return response()->json([
                'status' => $paymentIntent->status,
                'amount' => $paymentIntent->amount,
                'currency' => $paymentIntent->currency,
                'created' => $paymentIntent->created,
            ]);

        } catch (\Exception $e) {
            Log::error('Payment verification failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to verify payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

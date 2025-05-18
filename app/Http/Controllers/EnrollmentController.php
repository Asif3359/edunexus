<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class EnrollmentController extends Controller
{
    private $connections = ['dhaka', 'khulna', 'rajsahi'];

    private function findCourseInAllDatabases($course_id, $teacher_id = null)
    {
        foreach ($this->connections as $connection) {
            $query = \App\Models\Course::on($connection)->where('id', $course_id);
            if ($teacher_id) {
                $query->where('teacher_id', $teacher_id);
            }
            $course = $query->first();
            if ($course) {
                return [$course, $connection];
            }
        }
        return [null, null];
    }

    /**
     * Create a new enrollment.
     */
    public function store(Request $request)
    {
        try {
            // Get location first
            $location = strtolower($request->input('location'));
            if (! in_array($location, ['dhaka', 'khulna', 'rajsahi'])) {
                Log::warning('Invalid location attempted:', ['location' => $location]);
                return response()->json(['message' => 'Invalid database location'], 400);
            }

            // Switch DB connection
            Config::set('database.default', $location);
            DB::purge($location);
            DB::reconnect($location);

            // Now validate (uses correct DB)
            $validated = $request->validate([
                'student_id'  => 'required|integer',
                'course_id'   => 'required|integer|exists:courses,id',
                'teacher_id'  => 'required|integer|exists:users,user_id',
                'paid_amount' => 'required|numeric|min:0',
                'location'    => ['required', 'string', function ($attribute, $value, $fail) {
                    if (!in_array(strtolower($value), ['dhaka', 'khulna', 'rajsahi'])) {
                        $fail('The selected location is invalid.');
                    }
                }],
            ]);

            // Ensure location is lowercase for consistency
            $validated['location'] = strtolower($validated['location']);
            Log::debug('Enrollment request data:', $validated);

            // Check if already enrolled
            $existing = Enrollment::on($location)
                ->where('student_id', $validated['student_id'])
                ->where('course_id', $validated['course_id'])
                ->first();

            if ($existing) {
                Log::info('Duplicate enrollment attempt:', [
                    'student_id' => $validated['student_id'],
                    'course_id' => $validated['course_id']
                ]);
                return response()->json([
                    'message' => 'You are already enrolled in this course',
                ], 400);
            }

            // Get course and validate payment
            $course = Course::on($location)->findOrFail($validated['course_id']);
            if ($course->price != $validated['paid_amount']) {
                Log::warning('Invalid payment amount:', [
                    'expected' => $course->price,
                    'received' => $validated['paid_amount']
                ]);
                return response()->json([
                    'message' => 'Invalid payment amount',
                ], 400);
            }

            // Create enrollment
            DB::connection($location)->beginTransaction();
            try {
                $enrollment = Enrollment::on($location)->create([
                    'student_id'  => $validated['student_id'],
                    'course_id'   => $validated['course_id'],
                    'teacher_id'  => $validated['teacher_id'],
                    'enroll_date' => now(),
                    'paid_amount' => $validated['paid_amount'],
                    'location'    => $validated['location'],
                ]);
                DB::connection($location)->commit();

                Log::info('Enrollment created successfully:', [
                    'enrollment_id' => $enrollment->id,
                    'student_id' => $enrollment->student_id,
                    'course_id' => $enrollment->course_id
                ]);

                return response()->json([
                    'message' => 'Successfully enrolled in the course',
                    'data'    => $enrollment,
                ], 201);
            } catch (\Exception $e) {
                DB::connection($location)->rollBack();
                Log::error('Enrollment creation failed:', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Enrollment creation failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create enrollment',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all enrollments for the authenticated user.
     */
    public function index(Request $request)
    {
        try {
            $student_id = $request->query('student_id');
            $location = $request->query('location');

            $connection = strtolower($location);
            if (!array_key_exists($connection, config('database.connections'))) {
                return response()->json([
                    'message' => 'Invalid database location'
                ], 400);
            }

            Config::set('database.default', $connection);
            DB::purge($connection);
            DB::reconnect($connection);

            $enrollments = Enrollment::on($connection)
                ->with(['course', 'course.teacher'])
                ->where('student_id', $student_id)
                ->latest()
                ->get();

            return response()->json([
                'data' => $enrollments
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch enrollments: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch enrollments',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if user is enrolled in a specific course.
     */
    public function checkEnrollment($courseId, Request $request )
    {
        try {
            $student_id = $request->query('student_id') ;
            $location = $request->query('location');

            $connection = strtolower($location);
            if (!array_key_exists($connection, config('database.connections'))) {
                return response()->json([
                    'message' => 'Invalid database location'
                ], 400);
            }

            Config::set('database.default', $connection);
            DB::purge($connection);
            DB::reconnect($connection);

            $isEnrolled = Enrollment::on($connection)
                ->where('student_id', $student_id)
                ->where('course_id', $courseId)
                ->exists();

            return response()->json([
                'is_enrolled' => $isEnrolled
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to check enrollment status: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to check enrollment status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
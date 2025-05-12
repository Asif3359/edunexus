<?php

namespace App\Http\Controllers\course;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use App\Models\Course;
use App\Models\Module;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CourseController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        \Log::debug("Create Course Request", ['request' => $request->all()]);

        // Validate request
        $validated = $request->validate([
            'user_id'     => 'required|integer',
            'userName'    => 'required|string',
            'userEmail'   => 'required|email',
            'Location'    => 'required|string|in:Dhaka,Rajsahi,Khulna',
            'title'       => 'required|string|max:255',
            'description' => 'required|string',
            'thumbnail'   => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'price'       => 'required|numeric|min:0',
        ]);

        // \Log::debug("thumbnail", ['thumbnail' => $request->file('thumbnail')]);

        try {
            // Switch database connection based on location
            $connection = strtolower($validated['Location']);
            if (!array_key_exists($connection, config('database.connections'))) {
                throw new \Exception("Invalid database location");
            }

            Config::set('database.default', $connection);
            DB::purge($connection);
            DB::reconnect($connection);

            // Handle thumbnail upload
            $thumbnailUrl = null;
            if ($request->hasFile('thumbnail')) {
                $path = $request->file('thumbnail')->store('course-thumbnails', 'public');
                $thumbnailUrl = Storage::disk('public')->url($path);
            }

            // Find or create user
            $user = User::firstOrCreate(
                ['user_id' => $validated['user_id']],
                [
                    'name'     => $validated['userName'],
                    'email'    => $validated['userEmail'],
                    'role'     => $request->input('userRole', 'student'),
                    'Location' => $validated['Location'],
                    'password' => bcrypt(Str::random(16)),
                ]
            );

            // \Log::debug("thumbnailUrl", ['thumbnailUrl' => $thumbnailUrl]);

            // Create course
            $course = new Course([
                'title'       => $validated['title'],
                'description' => $validated['description'],
                'price'       => $validated['price'],
                'teacher_id'  => $user->user_id,
                'thumbnail'   => $thumbnailUrl,
            ]);

            $course->setConnection($connection);
            $course->save();

            return response()->json([
                'success' => true,
                'message' => 'Course created successfully',
                'course'  => $course,
                'user'    => $user,
            ]);

        } catch (\Exception $e) {
            Log::error('Course creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Course creation failed',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    public function index(Request $request): JsonResponse
    {
        try {
            // Validate location input
            $validated = $request->validate([
                'location' => 'required|string|in:Dhaka,Rajsahi,Khulna',
            ]);

            $connection = strtolower($validated['location']);

            if (!array_key_exists($connection, config('database.connections'))) {
                throw new \Exception("Invalid database location");
            }

            // Switch database connection
            Config::set('database.default', $connection);
            DB::purge($connection);
            DB::reconnect($connection);

            $courses = Course::with(['teacher' => function($query) {
                    $query->select('user_id', 'name', 'email');
                }])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'courses' => $courses,
                'location' => $validated['location'],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch courses', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch courses',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id, Request $request): JsonResponse
    {
        try {
            // Validate location
            $validated = $request->validate([
                'location' => 'required|string|in:Dhaka,Rajsahi,Khulna',
            ]);

            $connection = strtolower($validated['location']);

            if (!array_key_exists($connection, config('database.connections'))) {
                throw new \Exception("Invalid database location");
            }

            // Switch database connection
            Config::set('database.default', $connection);
            DB::purge($connection);
            DB::reconnect($connection);

            // Fetch course with related teacher
            $course = Course::with(['teacher' => function ($query) {
                    $query->select('user_id', 'name', 'email');
                }])
                ->findOrFail($id);

            return response()->json([
                'success'  => true,
                'message'  => 'Course fetched successfully',
                'course'   => $course,
                'location' => $validated['location'],
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Course not found',
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Failed to fetch course', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch course',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function addModule(Request $request): JsonResponse
    {

        \Log::debug("Add Module Request", ['request' => $request->all()]);
       // Step 1: Validate input (including location)
        $validated = $request->validate([
            'location' => 'required|string|in:Dhaka,Rajsahi,Khulna',
            'course_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'position' => 'required|integer',
        ]);



        // Step 2: Switch database connection
        $connection = strtolower($validated['location']);

        if (!array_key_exists($connection, config('database.connections'))) {
            return response()->json(['error' => 'Invalid database location.'], 400);
        }

        Config::set('database.default', $connection);
        DB::purge($connection);
        DB::reconnect($connection);

        // Step 3: Check if course exists in the selected database
        $courseExists = DB::connection($connection)->table('courses')->where('id', $validated['course_id'])->exists();

        if (!$courseExists) {
            return response()->json(['error' => 'Course ID not found in selected location.'], 404);
        }

        // Step 4: Create the module
        $module = Module::on($connection)->create([
            'course_id' => $validated['course_id'],
            'title' => $validated['title'],
            'position' => $validated['position'],
        ]);

        return response()->json([
            'message' => 'Module added successfully',
            'module' => $module
        ], 201);
    }

    public function getModules(Request $request, $courseId): JsonResponse
    {
        // Validate location
        $request->validate([
            'location' => 'required|string|in:Dhaka,Rajsahi,Khulna',
        ]);

        $location = strtolower($request->location);

        if (!array_key_exists($location, config('database.connections'))) {
            return response()->json(['error' => 'Invalid database location.'], 400);
        }

        // Switch DB connection
        Config::set('database.default', $location);
        DB::purge($location);
        DB::reconnect($location);

        // Check if course exists
        $courseExists = DB::connection($location)->table('courses')->where('id', $courseId)->exists();

        if (!$courseExists) {
            return response()->json(['error' => 'Course not found in selected location.'], 404);
        }

        // Fetch modules
        $modules = Module::on($location)
            ->where('course_id', $courseId)
            ->orderBy('position')
            ->get(['id as ModuleID', 'title as Title', 'position as Position']);

        return response()->json([
            'success' => true,
            'modules' => $modules
        ]);
    }

}

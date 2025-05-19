<?php

namespace App\Http\Controllers\course;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use App\Models\Course;
use App\Models\LiveClass;
use App\Models\Module;
use App\Models\Video;
use App\Models\Rating;
use App\Models\Enrollment;
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
use Illuminate\Support\Facades\Auth;

class CourseController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        Log::debug("Create Course Request", ['request' => $request->all()]);

        // Validate request
        $validated = $request->validate([
            'user_id'     => 'required|integer',
            'userName'    => 'required|string',
            'userEmail'   => 'required|email',
            'Location'    => 'required|string|in:Dhaka,Rajsahi,Khulna',
            'title'       => 'required|string|max:255',
            'category'    => 'required|string|in:Development,Business,"Finance & Accounting","IT & Software","Office Productivity","Personal Development",Design,Marketing,Lifestyle,"Photography & Video","Health & Fitness",Music,"Teaching & Academics"',
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
                $thumbnailUrl = asset('storage/' . $path);
            }

            // Find or create user
            $user = User::on($connection)->firstOrCreate(
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
            $course = Course::on($connection)->create([
                'title'       => $validated['title'],
                'description' => $validated['description'],
                'price'       => $validated['price'],
                'teacher_id'  => $user->user_id,
                'thumbnail'   => $thumbnailUrl,
            ]);


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
    public function index(Request $request,$user_id): JsonResponse
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
                ->where('teacher_id', $user_id)
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
            Log::error('Failed to fetch course', [
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

        Log::debug("Add Module Request", ['request' => $request->all()]);
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

    public function getModuleContent(Request $request, $moduleId): JsonResponse
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

        // Check if module exists
        $moduleExists = DB::connection($location)->table('modules')->where('id', $moduleId)->exists();

        if (!$moduleExists) {
            return response()->json(['error' => 'Module not found in selected location.'], 404);
        }

        // Fetch module content
        $moduleContent = Module::on($location)
            ->where('id', $moduleId)
            ->with('course')
            ->with('videos')
            ->with('liveClasses')
            ->first();

        return response()->json([
            'success' => true,
            'module' => $moduleContent
        ]);
    }

    public function addVideo(Request $request): JsonResponse
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

        // Validate input
        $validated = $request->validate([
            'module_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'video_url' => 'required|url',
            'position' => 'required|integer',
        ]);

        // Check if module exists
        $moduleExists = DB::connection($location)->table('modules')->where('id', $validated['module_id'])->exists();

        if (!$moduleExists) {
            return response()->json(['error' => 'Module not found in selected location.'], 404);
        }

        // Create video
        $video = Video::on($location)->create([
            'module_id' => $validated['module_id'],
            'title' => $validated['title'],
            'video_url' => $validated['video_url'],
            'position' => $validated['position'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Video added successfully',
            'video' => $video
        ], 201);
    }

    public function addLiveClass(Request $request): JsonResponse
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

        // Validate input
        $validated = $request->validate([
            'module_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'link' => 'required|url',
            'duration' => 'required|integer',
            'schedule' => 'required|date',
            'location' => 'required|string|in:Dhaka,Rajsahi,Khulna',
        ]);

        // Check if module exists
        $moduleExists = DB::connection($location)->table('modules')->where('id', $validated['module_id'])->exists();

        if (!$moduleExists) {
            return response()->json(['error' => 'Module not found in selected location.'], 404);
        }

        // Create live class
        $liveClass = LiveClass::on($location)->create([
            'module_id' => $validated['module_id'],
            'title' => $validated['title'],
            'link' => $validated['link'],
            'duration' => $validated['duration'],
            'schedule' => $validated['schedule'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Live class added successfully',
            'live_class' => $liveClass
        ], 201);
    }

    public function getScheduledClass(Request $request, $userID): JsonResponse
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

        // Get user
        $user = User::on($location)->where('user_id', $userID)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found in selected location.'], 404);
        }

        // Get teacher's courses
        $teacherCourses = Course::on($location)
            ->where('teacher_id', $user->user_id)
            ->pluck('id');

        // Get modules from teacher's courses
        $teacherModules = Module::on($location)
            ->whereIn('course_id', $teacherCourses)
            ->pluck('id');

        // Fetch upcoming live classes for teacher's modules
        $scheduledClasses = LiveClass::on($location)
            ->whereIn('module_id', $teacherModules)
            ->where('schedule', '>', now())
            ->with(['module.course']) // Eager load module and course
            ->orderBy('schedule', 'asc')
            ->get();

        // Map the response to include course and module titles
        $result = $scheduledClasses->map(function ($class) {
            return [
                'id' => $class->id,
                'title' => $class->title,
                'link' => $class->link,
                'duration' => $class->duration,
                'schedule' => $class->schedule,
                'module_id' => $class->module_id,
                'module_title' => $class->module ? $class->module->title : null,
                'course_id' => $class->module && $class->module->course ? $class->module->course->id : null,
                'course_title' => $class->module && $class->module->course ? $class->module->course->title : null,
            ];
        });

        return response()->json([
            'success' => true,
            'scheduled_classes' => $result
        ]);
    }

    public function getLiveClass(Request $request, $classId): JsonResponse
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

        // Check if live class exists
        $liveClassExists = DB::connection($location)->table('live_classes')->where('id', $classId)->exists();

        if (!$liveClassExists) {
            return response()->json(['error' => 'Live class not found in selected location.'], 404);
        }

        // Fetch live class
        $liveClass = LiveClass::on($location)
            ->where('id', $classId)
            ->first();

        return response()->json([
            'success' => true,
            'live_class' => $liveClass
        ]);
    }

    public function getLiveClassSchedule(Request $request, $userId): JsonResponse
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

        // Check if user exists
        $userExists = DB::connection($location)->table('users')->where('id', $userId)->exists();

        if (!$userExists) {
            return response()->json(['error' => 'User not found in selected location.'], 404);
        }

        // Fetch live class schedule
        $liveClassSchedule = LiveClass::on($location)
            ->where('user_id', $userId)
            ->get();

        return response()->json([
            'success' => true,
            'live_class_schedule' => $liveClassSchedule
        ]);
    }

    protected $connections = ['dhaka', 'khulna', 'rajsahi'];

    public function topRated()
    {
        $allCourses = collect();

        foreach ($this->connections as $connection) {
            try {
                $courses = Course::on($connection)
                    ->with(['teacher', 'modules.videos'])
                    ->addSelect([
                        'average_rating' => \App\Models\Rating::on($connection)
                            ->selectRaw('AVG(rating)')
                            ->whereColumn('course_id', 'courses.id')
                    ])
                    ->orderBy('average_rating', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(function ($course) use ($connection) {
                        $formatted = $this->formatCourse($course);
                        $formatted['location'] = ucfirst($connection);
                        $formatted['rating'] = round($course->average_rating ?? 0, 1);
                        return $formatted;
                    });

                $allCourses = $allCourses->merge($courses);
            } catch (\Exception $e) {
                // Log error and continue with next connection
                Log::error("Error fetching top rated courses from {$connection}: " . $e->getMessage());
                continue;
            }
        }

        return response()->json($allCourses->sortByDesc('rating')->take(5)->values());
    }

    public function topSelling()
    {
        $allCourses = collect();

        foreach ($this->connections as $connection) {
            try {
                $courses = Course::on($connection)
                    ->with(['teacher', 'modules.videos'])
                    ->addSelect([
                        'enrollments_count' => Enrollment::on($connection)
                            ->selectRaw('COUNT(*)')
                            ->whereColumn('course_id', 'courses.id')
                    ])
                    ->orderBy('enrollments_count', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(function ($course) use ($connection) {
                        $formatted = $this->formatCourse($course);
                        $formatted['location'] = ucfirst($connection);
                        $formatted['sellCount'] = $course->enrollments_count ?? 0;
                        return $formatted;
                    });

                $allCourses = $allCourses->merge($courses);
            } catch (\Exception $e) {
                Log::error("Error fetching top selling courses from {$connection}: " . $e->getMessage());
                continue;
            }
        }

        return response()->json($allCourses->sortByDesc('sellCount')->take(5)->values());
    }

    public function suggested(Request $request)
    {
        $allCourses = collect();

        foreach ($this->connections as $connection) {
            $courses = Course::on($connection)
                ->with(['teacher', 'modules.videos'])
                ->inRandomOrder()
                ->limit(2) // Get fewer from each to have variety
                ->get()
                ->map(function ($course) use ($connection) {
                    $formatted = $this->formatCourse($course);
                    $formatted['location'] = ucfirst($connection);
                    return $formatted;
                });

            $allCourses = $allCourses->merge($courses);
        }

        // Shuffle and take 5
        $suggestedCourses = $allCourses->shuffle()->take(5)->values();

        return response()->json($suggestedCourses);
    }

    public function allCourses(Request $request)
    {
        $allCourses = collect();

        foreach ($this->connections as $connection) {
            try {
                $courses = Course::on($connection)
                    ->with(['teacher', 'modules.videos'])
                    ->addSelect([
                        'average_rating' => Rating::on($connection)
                            ->selectRaw('AVG(rating)')
                            ->whereColumn('course_id', 'courses.id'),
                        'enrollments_count' => Enrollment::on($connection)
                            ->selectRaw('COUNT(*)')
                            ->whereColumn('course_id', 'courses.id')
                    ])
                    ->get()
                    ->map(function ($course) use ($connection) {
                        return $this->formatCourse($course, $connection);
                    });

                $allCourses = $allCourses->merge($courses);
            } catch (\Exception $e) {
                Log::error("Error fetching courses from {$connection}: " . $e->getMessage());
                continue;
            }
        }

        return response()->json($allCourses->shuffle()->values());
    }

    public function categories()
    {
        $allCategories = collect();

        foreach ($this->connections as $connection) {
            try {
                $categories = Course::on($connection)
                    ->distinct()
                    ->pluck('category')
                    ->values();

                $allCategories = $allCategories->merge($categories);
            } catch (\Exception $e) {
                Log::error("Error fetching categories from {$connection}: " . $e->getMessage());
                continue;
            }
        }

        $uniqueCategories = $allCategories->unique()->values();

        return response()->json($uniqueCategories);
    }

    public function daynamicCourse($location, $id, $teacherEmail)
    {
        $location = strtolower($location);
        $availableLocations = ['dhaka', 'rajsahi', 'khulna'];

        if (!in_array($location, $availableLocations)) {
            return response()->json(['error' => 'Invalid location'], 400);
        }

        \Log::debug("Requested ID", ['id' => $id]);
        \Log::debug("Requested Teacher Email", ['teacher_email' => $teacherEmail]);

        try {
            foreach ($availableLocations as $conn) {
                \Log::debug("Searching in database", ['db' => $conn]);

                $course = Course::on($conn)
                    ->with(['teacher', 'modules.videos'])
                    ->where('id', $id)
                    ->whereHas('teacher', function ($query) use ($teacherEmail) {
                        $query->where('email', $teacherEmail);
                    })
                    ->addSelect([
                        'average_rating' => Rating::on($conn)
                            ->selectRaw('AVG(rating)')
                            ->whereColumn('course_id', 'courses.id'),
                        'enrollments_count' => Enrollment::on($conn)
                            ->selectRaw('COUNT(*)')
                            ->whereColumn('course_id', 'courses.id')
                    ])
                    ->first();

                if ($course) {
                    return response()->json($this->formatCourseDetails($course, $conn));
                }
            }

            return response()->json(['error' => 'Course not found in any location'], 404);

        } catch (\Exception $e) {
            \Log::error("Error fetching course: " . $e->getMessage());
            return response()->json(['error' => 'Server error'], 500);
        }
    }


    private function formatCourseDetails($course, $connection)
    {
        return [
            'id' => $course->id,
            'title' => $course->title,
            'description' => $course->description,
            'category' => $course->category,
            'teacher_id' => $course->teacher_id,
            'instructor' => $course->teacher->name,
            'email' => $course->teacher->email,
            'price' => $course->price,
            'rating' => round($course->average_rating ?? 0, 1),
            'enrollments' => $course->enrollments_count ?? 0,
            'duration' => $this->calculateDurationforDaynamic($course->modules),
            'thumbnail' => $course->thumbnail,
            'location' => ucfirst($connection),
            'modules' => $course->modules->map(function ($module) {
                return [
                    'title' => $module->title,
                    'videos' => $module->videos->map(function ($video) {
                        return [
                            'title' => $video->title,
                            'url' => $video->video_url,
                            'duration' => $video->duration,
                        ];
                    }),
                    'liveClasses' => $module->liveClasses->map(function ($liveClass) {
                        return [
                            'title' => $liveClass->title,
                            'link' => $liveClass->link,
                            'duration' => $liveClass->duration,
                            'schedule' => $liveClass->schedule,
                        ];
                    }),
                ];
            }),
        ];
    }

    private function calculateDurationforDaynamic($modules)
    {
        $totalMinutes = $modules->sum(function ($module) {
            return $module->videos->sum('duration');
        });

        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        return $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
    }

    private function formatCourse($course)
    {
        $firstVideoUrl = optional(optional($course->modules->first())->videos->first())->video_url ?? '';

        return [
            'id' => $course->id,
            'title' => $course->title,
            'teacherEmail' => $course->teacher->email,
            'description' => $course->description,
            'price' => $course->price,
            'thumbnail' => $course->thumbnail,
            'category' => $course->category,
            'instructor' => $course->teacher->name,
            'rating' => round($course->ratings_avg_rating ?? 0, 1),
            'sellCount' => $course->enrollments_count ?? 0,
            'modules' => [
                [
                    'videos' => [
                        [
                            'url' => $firstVideoUrl
                        ]
                    ]
                ]
            ]
        ];
    }
    private function calculateDuration($modules)
    {
        if (!$modules) return '0 hours';

        $totalMinutes = 0;
        foreach ($modules as $module) {
            if ($module->videos) {
                foreach ($module->videos as $video) {
                    // Assuming each video has a duration field
                    $totalMinutes += $video->duration ?? 0;
                }
            }
        }

        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        if ($hours > 0) {
            return "{$hours} hour" . ($hours > 1 ? 's' : '') .
                   ($minutes > 0 ? " {$minutes} min" : '');
        }

        return "{$minutes} min";
    }

    public function fullCourse($courseId, $location)
    {
        try {

            $location = strtolower($location);


            Config::set('database.default', $location);
            DB::purge($location);
            DB::reconnect($location);

            $course = Course::on($location)
                ->with(['modules.videos', 'modules.liveClasses'])
                ->findOrFail($courseId);

            Log::info('Course fetch successful:', [
                'courseId' => $courseId,
                'location' => $location,
                'course_exists' => !is_null($course)
            ]);

            return response()->json($course);

        } catch (\Exception $e) {
            Log::error('Course fetch error:', [
                'error' => $e->getMessage(),
                'courseId' => $courseId,
                'location' => $location ?? 'not set',
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Failed to fetch course details',
                'error' => $e->getMessage(),
                'debug_info' => [
                    'courseId' => $courseId,
                    'location' => $location ?? 'not set',
                ]
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\profile;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Skill;
use App\Models\Interest;
use App\Models\SocialLink;
use App\Models\Education;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PrimaryProfileSetupController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // Validate request
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'userName' => 'required|string',
            'userEmail' => 'required|email',
            'Location' => 'required|string|in:Dhaka,Rajsahi,Khulna',
            'skills' => 'required|array|min:1',
            'interests' => 'required|array|min:1',
            'socialLinks' => 'required|array|min:1',
            'education' => 'required|array|min:1',
            'education.*.degree' => 'required|string',
            'education.*.institution' => 'required|string',
            'education.*.year' => 'required|integer|min:1900|max:' . (date('Y') + 5),
        ]);

        try {
            // Switch database
            $connection = strtolower($validated['Location']);
            if (!array_key_exists($connection, config('database.connections'))) {
                throw new \Exception("Invalid database location");
            }

            Config::set('database.default', $connection);
            DB::purge($connection);
            DB::reconnect($connection);

            // Find or create user
            $user = User::firstOrCreate(
                ['user_id' => $validated['user_id']],
                [
                    'name' => $validated['userName'],
                    'email' => $validated['userEmail'],
                    'role' => $request->input('userRole', 'student'),
                    'Location' => $validated['Location'],
                    'password' => bcrypt(Str::random(16)), // Temporary password
                ]
            );

            // Process relationships
            $this->syncSkills($user, $validated['skills']);
            $this->syncInterests($user, $validated['interests']);
            $this->syncSocialLinks($user, $validated['socialLinks']);
            $this->syncEducation($user, $validated['education']);

            return response()->json([
                'success' => true,
                'message' => 'Profile saved successfully',
                'user_id' => $user->user_id
            ]);

        } catch (\Exception $e) {
            Log::error('Profile save failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Profile save failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function syncSkills(User $user, array $skills): void
    {
        $skillIds = [];
        foreach ($skills as $skill) {
            if (!empty(trim($skill))) {
                $skillModel = Skill::firstOrCreate(['skill_name' => trim($skill)]);
                $skillIds[] = $skillModel->id; // Changed from SkillID to id
            }
        }
        $user->skills()->sync($skillIds);
    }

    private function syncInterests(User $user, array $interests): void
    {
        $interestIds = [];
        foreach ($interests as $interest) {
            if (!empty(trim($interest))) {
                $interestModel = Interest::firstOrCreate(['interest_name' => trim($interest)]);
                $interestIds[] = $interestModel->id; // Changed from InterestID to id
            }
        }
        $user->interests()->sync($interestIds);
    }

    private function syncSocialLinks(User $user, array $links): void
    {
        $linkIds = [];
        foreach ($links as $link) {
            if (!empty(trim($link))) {
                $linkModel = SocialLink::firstOrCreate(['social_link' => trim($link)]);
                $linkIds[] = $linkModel->id; // Changed from SocialLinkID to id
            }
        }
        $user->socialLinks()->sync($linkIds);
    }

    private function syncEducation(User $user, array $education): void
    {
        $educationIds = [];
        foreach ($education as $edu) {
            if (!empty($edu['degree']) && !empty($edu['institution'])) {
                $education = Education::create([
                    'degree' => $edu['degree'],
                    'institution' => $edu['institution'],
                    'year' => $this->parseYear($edu['year'] ?? null),
                    'description' => $edu['description'] ?? null,
                ]);
                $educationIds[] = $education->id; // Changed from EducationID to id
            }
        }
        $user->educations()->sync($educationIds);
    }

    private function parseYear($year): ?int
    {
        if (empty($year)) return null;

        if (is_numeric($year)) {
            return (int) $year;
        }

        if (preg_match('/(\d{4})/', $year, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }


    /**
     * Get user profile by user ID
     *
     * @param Request $request
     * @param int $userId
     * @return JsonResponse
     */
    public function show(Request $request, $userId): JsonResponse
    {

        $userId = (int) $userId;
        $location = $request->header('Location');

        // \Log::info('Fetching user profile', [
        //     'user_id' => $userId,
        //     'location' => $location
        // ]);

        // return response()->json([
        //     'success' => false,
        //     'message' => 'This feature is temporarily disabled.'
        // ], 200);

        if (!in_array($location, ['Dhaka', 'Rajsahi', 'Khulna'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid location provided.'
            ], 400);
        }

        try {
            $connection = strtolower($location);

            if (!array_key_exists($connection, config('database.connections'))) {
                throw new \Exception("Invalid database location");
            }

            Config::set('database.default', $connection);
            DB::purge($connection);
            DB::reconnect($connection);

            $user = User::with(['skills', 'interests', 'socialLinks', 'educations', 'studentProfile'])
                ->where('user_id', $userId)
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.'
                ], 200);
            }

            return response()->json([
                'success' => true,
                'data' => $user
            ]);
        } catch (\Exception $e) {
            Log::error('Profile fetch failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $userId,
                'location' => $location
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Profile fetch failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public function update(Request $request): JsonResponse
    {
        // Validate request
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'userName' => 'required|string',
            'userEmail' => 'required|email',
            'Location' => 'required|string|in:Dhaka,Rajsahi,Khulna',
            'profile_picture' =>'required|string',
            'mobile' => 'required|string',
            'bio' => 'required|string',
            'skills' => 'required|array|min:1',
            'interests' => 'required|array|min:1',
            'socialLinks' => 'required|array|min:1',
            'education' => 'required|array|min:1',
            'education.*.degree' => 'required|string',
            'education.*.institution' => 'required|string',
            'education.*.year' => 'required|integer|min:1900|max:' . (date('Y') + 5),
            'education.*.description' => 'nullable|string',

        ]);

        try {
            // Switch database
            $connection = strtolower($validated['Location']);
            if (!array_key_exists($connection, config('database.connections'))) {
                throw new \Exception("Invalid database location");
            }

            Config::set('database.default', $connection);
            DB::purge($connection);
            DB::reconnect($connection);

            // Find the user
            $user = User::where('user_id', $validated['user_id'])->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }

            // Update user info
            $user->update([
                'name' => $validated['userName'],
                'email' => $validated['userEmail'],
                'Location' => $validated['Location'],
            ]);

            // Append relationships without detaching old ones
            $this->appendStudentProfile($user, $validated['profile_picture'], $validated['mobile'], $validated['bio']);

            $this->appendSkills($user, $validated['skills']);
            $this->appendInterests($user, $validated['interests']);
            $this->appendSocialLinks($user, $validated['socialLinks']);
            $this->appendEducation($user, $validated['education']);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'user_id' => $user->user_id
            ]);

        } catch (\Exception $e) {
            Log::error('Profile update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Profile update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    private function appendSkills(User $user, array $skills): void
    {
        $skillIds = [];
        foreach ($skills as $skill) {
            if (!empty(trim($skill))) {
                $skillModel = Skill::firstOrCreate(['skill_name' => trim($skill)]);
                $skillIds[] = $skillModel->id;
            }
        }
        $user->skills()->syncWithoutDetaching($skillIds);
    }

    private function appendInterests(User $user, array $interests): void
    {
        $interestIds = [];
        foreach ($interests as $interest) {
            if (!empty(trim($interest))) {
                $interestModel = Interest::firstOrCreate(['interest_name' => trim($interest)]);
                $interestIds[] = $interestModel->id;
            }
        }
        $user->interests()->syncWithoutDetaching($interestIds);
    }

    private function appendSocialLinks(User $user, array $links): void
    {
        $linkIds = [];
        foreach ($links as $link) {
            if (!empty(trim($link))) {
                $linkModel = SocialLink::firstOrCreate(['social_link' => trim($link)]);
                $linkIds[] = $linkModel->id;
            }
        }
        $user->socialLinks()->syncWithoutDetaching($linkIds);
    }

    private function appendEducation(User $user, array $education): void
    {
        foreach ($education as $edu) {
            if (!empty($edu['degree']) && !empty($edu['institution'])) {
                $educationModel = Education::firstOrCreate([
                    'degree' => $edu['degree'],
                    'institution' => $edu['institution'],
                    'year' => $this->parseYear($edu['year'] ?? null),
                    'description' => $edu['description'] ?? null,
                ]);
                $user->educations()->syncWithoutDetaching([$educationModel->id]);
            }
        }
    }

    private function appendStudentProfile(User $user, string $profile_picture, string $mobile, string $bio): void
    {
        // Assuming you have a StudentProfile model
        $studentProfile = $user->studentProfile()->firstOrCreate([
            'student_id' => $user->user_id,
        ]);

        $studentProfile->update([
            'profile_picture' => $profile_picture,
            'mobile' => $mobile,
            'bio' => $bio,
        ]);
    }

}

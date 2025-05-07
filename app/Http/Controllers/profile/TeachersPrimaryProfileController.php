<?php

namespace App\Http\Controllers\profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\Skill;
use App\Models\Interest;
use App\Models\SocialLink;
use App\Models\Education;

class TeachersPrimaryProfileController extends Controller
{
    //


    public function show(Request $request, $userId): JsonResponse
    {

        $userId = (int) $userId;
        $location = $request->header('Location');


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

            $user = User::with(['skills', 'interests', 'socialLinks', 'educations', 'teacherProfile'])
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
            $this->appendTeacherProfile($user, $validated['profile_picture'], $validated['mobile'], $validated['bio']);

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

    private function appendTeacherProfile(User $user, string $profile_picture, string $mobile, string $bio): void
    {
        // Assuming you have a teacherProfile model
        $teacherProfile = $user->teacherProfile()->firstOrCreate([
            'teacher_id' => $user->user_id,
        ]);

        $teacherProfile->update([
            'profile_picture' => $profile_picture,
            'mobile' => $mobile,
            'bio' => $bio,
        ]);
    }

}

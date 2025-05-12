<?php
namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Skill;
use App\Models\Interest;
use App\Models\SocialLink;
use App\Models\Education;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\Course;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $primaryKey = 'user_id';
    public $incrementing  = true;  // or false if it's not auto-incrementing
    protected $keyType    = 'int'; // or 'string' if it's a UUID
    protected $fillable   = [
        'user_id',
        'name',
        'email',
        'password',
        'role',     // ✅ Add this line
        'Location', // ✅ Add this line
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }
    // Define relationships
    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class, 'student_id', 'user_id');
    }
    public function teacherProfile(): HasOne
    {
        return $this->hasOne(TeacherProfile::class, 'teacher_id', 'user_id');
    }

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'user_skills', 'user_id', 'skill_id');
    }

    public function interests()
    {
        return $this->belongsToMany(Interest::class, 'user_interests', 'user_id', 'interest_id');
    }

    public function socialLinks()
    {
        return $this->belongsToMany(SocialLink::class, 'user_social_links', 'user_id', 'social_link_id');
    }

    public function educations()
    {
        return $this->belongsToMany(Education::class, 'user_educations', 'user_id', 'education_id');
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'teacher_id', 'user_id');
    }


}

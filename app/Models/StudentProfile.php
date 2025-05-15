<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentProfile extends Model
{
    protected $primaryKey = 'student_id';
    // teacher_id
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'student_id',
        'profile_picture',
        'mobile',
        'bio',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id', 'user_id');
    }

    public function liveClasses(): HasMany
    {
        return $this->hasMany(LiveClass::class, 'student_id', 'user_id');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class, 'student_id', 'user_id');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class, 'student_id', 'user_id');
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'student_id', 'user_id');
    }

    public function skills(): HasMany
    {
        return $this->hasMany(Skill::class, 'student_id', 'user_id');
    }

    public function interests(): HasMany
    {
        return $this->hasMany(Interest::class, 'student_id', 'user_id');
    }

    public function educations(): HasMany
    {
        return $this->hasMany(Education::class, 'student_id', 'user_id');
    }

    public function socialLinks(): HasMany
    {
        return $this->hasMany(SocialLink::class, 'student_id', 'user_id');
    }
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id', 'user_id');
    }

}

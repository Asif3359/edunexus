<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'category',
        'thumbnail',
        'price',
        'teacher_id',
    ];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id', 'user_id');
    }
    public function liveClasses()
    {
        return $this->hasMany(LiveClass::class, 'module_id', 'id');
    }
    public function modules()
    {
        return $this->hasMany(Module::class, 'course_id', 'id');
    }
    public function students()
    {
        return $this->belongsToMany(User::class, 'course_student', 'course_id', 'student_id');
    }

      public function ratings()
    {
        return $this->hasMany(Rating::class);
    }

    /**
     * Get all enrollments for the course
     */
    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Get all videos through modules
     */
    public function videos()
    {
        return $this->hasManyThrough(
            Video::class,
            Module::class,
            'course_id', // Foreign key on modules table
            'module_id', // Foreign key on videos table
            'id',        // Local key on courses table
            'id'         // Local key on modules table
        );
    }



}

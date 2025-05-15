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
    public function videos()
    {
        return $this->hasMany(Video::class, 'module_id', 'id');
    }
    public function modules()
    {
        return $this->hasMany(Module::class, 'course_id', 'id');
    }
    public function students()
    {
        return $this->belongsToMany(User::class, 'course_student', 'course_id', 'student_id');
    }

}

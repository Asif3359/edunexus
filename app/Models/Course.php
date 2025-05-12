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

    public function modules()
    {
        return $this->hasMany(Module::class);
    }
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id', 'user_id');
    }
}

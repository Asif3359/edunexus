<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentProfile extends Model
{
    protected $primaryKey = 'student_id';
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
}

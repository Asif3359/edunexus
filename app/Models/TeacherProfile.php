<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TeacherProfile extends Model
{
    protected $primaryKey = 'teacher_id';
    // teacher_id
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'teacher_id',
        'profile_picture',
        'mobile',
        'bio',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id', 'user_id');
    }
}

<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Experience extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization',
        'role',
        'duration',
        'description',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_experiences')
            ->withTimestamps();
    }

    public function userExperiences()
    {
        return $this->hasMany(UserExperiences::class);
    }
}

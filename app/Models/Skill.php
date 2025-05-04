<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    protected $primaryKey = 'id';
    protected $fillable = ['skill_name'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_skills');
    }
}

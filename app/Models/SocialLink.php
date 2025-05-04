<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialLink extends Model
{
    protected $primaryKey = 'id';
    protected $fillable = ['social_link'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_social_links');
    }
}

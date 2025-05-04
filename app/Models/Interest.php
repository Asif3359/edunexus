<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Interest extends Model
{
    protected $primaryKey = 'id';
    protected $fillable = ['interest_name'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_interests');
    }
}

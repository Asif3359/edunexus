<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Education extends Model
{
    protected $table = 'educations'; // Explicitly set table name
    protected $primaryKey = 'id';
    protected $fillable = ['degree', 'institution', 'year', 'description'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_educations');
    }
}

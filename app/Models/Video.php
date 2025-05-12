<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Video extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_id',
        'title',
        'video_url',
        'position',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }
}

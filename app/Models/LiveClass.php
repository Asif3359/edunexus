<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LiveClass extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_id',
        'title',
        'schedule',
        'link',
        'duration',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }
}

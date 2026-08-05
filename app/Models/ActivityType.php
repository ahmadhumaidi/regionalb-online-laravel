<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityType extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
        'color',
    ];
}

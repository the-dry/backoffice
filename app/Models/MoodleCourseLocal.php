<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MoodleCourseLocal extends Model
{
    use HasFactory;

    protected $table = 'moodle_courses_local';

    protected $fillable = [
        'moodle_id',
        'shortname',
        'fullname',
        'summary',
        'format',
        'visible',
        'startdate',
        'enddate',
        'raw_data',
    ];

    protected $casts = [
        'moodle_id' => 'integer',
        'visible' => 'boolean',
        'startdate' => 'datetime',
        'enddate' => 'datetime',
        'raw_data' => 'array',
    ];
}

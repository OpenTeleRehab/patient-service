<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    const ACTIVITY_TYPE_EXERCISE = 'exercise';
    const ACTIVITY_TYPE_MATERIAL = 'material';

    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'treatment_plan_id',
        'week',
        'day',
        'activity_id',
        'completed',
        'pain_level',
        'sets',
        'reps',
        'type',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var boolean
     */
    public $timestamps = false;
}

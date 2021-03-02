<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    use HasFactory;

    const ACTIVITY_TYPE_EXERCISE = 'exercise';
    const ACTIVITY_TYPE_MATERIAL = 'material';
    const ACTIVITY_TYPE_QUESTIONNAIRE = 'questionnaire';
    const ACTIVITY_TYPE_GOAL = 'goal';

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
        'submitted_date',
        'satisfaction',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var boolean
     */
    public $timestamps = false;

    /**
     * The attributes that should be cast to native types.
     * This format will be used when the model is serialized to an array or JSON
     *
     * @var array
     */
    protected $casts = [
        'submitted_date' => 'datetime:d/m/Y',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function answers()
    {
        return $this->hasMany(QuestionnaireAnswer::class);
    }
}

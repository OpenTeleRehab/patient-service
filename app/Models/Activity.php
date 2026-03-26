<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity as ActivityLog;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Support\Facades\Auth;
use App\Models\ExtendActivity;

class Activity extends Model
{
    use HasFactory, LogsActivity;

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
        'created_by',
        'completed_sets',
        'completed_reps',
        'additional_information'
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
     * Get the options for activity logging.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->logExcept(['id', 'created_at', 'updated_at']);
    }

    /**
     * Modify the activity properties before it is saved.
     *
     * @param \Spatie\Activitylog\Models\Activity $activity
     * @return void
     */
    public function tapActivity(ActivityLog $activity)
    {
        $authUser = Auth::user();
        if ($authUser->therapist_user_id || $authUser->admin_user_id) {
            $activity->causer_id = $authUser->therapist_user_id ?? $authUser->admin_user_id;
            $activity->log_name = $authUser->therapist_user_id ? ExtendActivity::THERAPIST_SERVICE : ExtendActivity::ADMIN_SERVICE;
            $activity->country_id = $authUser->country_id;
            $activity->clinic_id = $authUser->clinic_id ?: null;
            $activity->phc_service_id = $authUser->phc_service_id ?: null;
            $activity->province_id = $authUser->province_id;
            $activity->region_id = $authUser->region_id;
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function answers()
    {
        return $this->hasMany(QuestionnaireAnswer::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\belongsTo
     */
    public function treatmentPlan()
    {
        return $this->belongsTo(TreatmentPlan::class);
    }
}

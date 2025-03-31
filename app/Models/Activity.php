<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity as ActivityLog;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

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
    public function tapActivity(ActivityLog $activity, string $eventName)
    {
        $this->refresh();
        $therapist = null;
        $patient = $this->treatmentPlan->user;
        $access_token = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);
        $response = Http::withToken($access_token)->get(env('THERAPIST_SERVICE_URL') . '/therapist/by-id', [
            'id' => $this->created_by,
        ]);
        if (!empty($response) && $response->successful()) {
            $therapist = json_decode($response);
        }
        if ($eventName === 'updated') {
            $activity->causer_id = $this->completed ? $patient->id : $this->created_by;
            $activity->full_name = $this->completed ? $patient->identity : ($therapist ? $therapist->last_name . ' ' . $therapist->first_name : null);
            $activity->group = $this->completed ? User::GROUP_PATIENT : User::GROUP_THERAPIST;
        } else {
            $activity->causer_id = $this->type === self::ACTIVITY_TYPE_GOAL ? $patient->id : $this->created_by;
            $activity->full_name = $this->type === self::ACTIVITY_TYPE_GOAL ? $patient->identity : ($therapist ? $therapist->last_name . ' ' . $therapist->first_name : null); 
            $activity->group = $this->type === self::ACTIVITY_TYPE_GOAL ? User::GROUP_PATIENT : User::GROUP_THERAPIST;
        }
        $activity->clinic_id = $this->type === self::ACTIVITY_TYPE_GOAL ? $patient->clinic_id : ($therapist ? $therapist->clinic_id : null);
        $activity->country_id = self::ACTIVITY_TYPE_GOAL ? $patient->country_id : ($therapist ? $therapist->country_id : null);
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

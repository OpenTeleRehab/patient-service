<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Activity as ActivityLog;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Support\Facades\Auth;

class AssistiveTechnology extends Model
{
    use SoftDeletes, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'assistive_technology_id',
        'patient_id',
        'therapist_id',
        'appointment_id',
        'provision_date',
        'follow_up_date',
    ];

    /**
     * The attributes that should be cast to native types.
     * This format will be used when the model is serialized to an array or JSON
     *
     * @var array
     */
    protected $casts = [
        'provision_date' => 'date:Y-m-d',
        'follow_up_date' => 'date:Y-m-d',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['follow_up_date'];

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
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function appointment()
    {
        return $this->hasOne(Appointment::class, 'id', 'appointment_id');
    }

    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        self::deleted(function ($assistive_technology) {
            Appointment::where('id', $assistive_technology->appointment_id)->delete();
        });
    }
}

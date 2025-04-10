<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity as ActivityLog;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class TreatmentPlan extends Model
{
    use LogsActivity;

    const STATUS_PLANNED = 'planned';
    const STATUS_ON_GOING = 'on_going';
    const STATUS_FINISHED = 'finished';
    const FILTER_STATUS_FINISHED = 1;
    const FILTER_STATUS_PLANNED = 2;
    const FILTER_STATUS_ON_GOING = 3;
    const PAIN_THRESHOLD_LIMIT = 'pain_threshold_limit';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
        'patient_id',
        'start_date',
        'end_date',
        'status',
        'total_of_weeks',
        'created_by',
        'disease_id',
    ];

    /**
     * The attributes that should be cast to native types.
     * This format will be used when the model is serialized to an array or JSON
     *
     * @var array
     */
    protected $casts = [
        'start_date' => 'datetime:d/m/Y',
        'end_date' => 'datetime:d/m/Y',
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
        $therapist = null;
        $therapistId = $this->user->therapist_id;
        $access_token = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);
        $response = Http::withToken($access_token)->get(env('THERAPIST_SERVICE_URL') . '/therapist/by-id', [
            'id' => $therapistId,
        ]);
        if (!empty($response) && $response->successful()) {
            $therapist = json_decode($response);
        }
        $activity->causer_id = $therapist ? $therapist->id : null;
        $activity->full_name = $therapist ? $therapist->last_name . ' ' . $therapist->first_name : null; 
        $activity->clinic_id = $therapist ? $therapist->clinic_id : null;
        $activity->country_id = $therapist ? $therapist->country_id : null;
        $activity->group = $therapist ? User::GROUP_THERAPIST : null;
    }

    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // Set default order.
        static::addGlobalScope('order', function (Builder $builder) {
            $builder->orderBy('start_date', 'desc');
            $builder->orderBy('name');
        });

        self::deleting(function ($treatmentPlan) {
            try {
                Goal::where('treatment_plan_id', $treatmentPlan->id)->delete();
                Activity::where('treatment_plan_id', $treatmentPlan->id)->delete();
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            }
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function goals()
    {
        return $this->hasMany(Goal::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function activities()
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\belongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'patient_id', 'id');
    }
}

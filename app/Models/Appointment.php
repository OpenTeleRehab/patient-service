<?php

namespace App\Models;

use App\Helpers\TranslationHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Events\PodcastNotificationEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Appointment extends Model
{
    use HasFactory, LogsActivity;

    const STATUS_INVITED = 'invited';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REJECTED = 'rejected';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'therapist_id',
        'patient_id',
        'therapist_status',
        'patient_status',
        'start_date',
        'end_date',
        'note',
        'created_by_therapist',
        'unread',
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
     * The attributes that should be cast to native types.
     * This format will be used when the model is serialized to an array or JSON
     *
     * @var array
     */
    protected $casts = [
        'start_date' => 'datetime:Y-m-d H:i:s',
        'end_date' => 'datetime:Y-m-d H:i:s',
        'created_by_therapist' => 'boolean',
    ];

    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('where', function (Builder $builder) {
            // Prevent if the deleted patient.
            // TODO: Remove appointment if patient deleted.
            $builder->has('patient');
        });

        self::created(function ($appointment) {
            try {
                $translations = TranslationHelper::getTranslations($appointment->patient->language_id);
                self::notification($appointment, $translations['appointment.invitation_appointment_with'] . ' ' . $appointment->patient->first_name . ' ' . $appointment->patient->last_name);
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            }
        });

        self::updated(function ($appointment) {
            if ($appointment->assistiveTechnology) {
                $assistiveTechnology = AssistiveTechnology::where('appointment_id', $appointment->id)->first();
                $assistiveTechnology->update(['follow_up_date' => date_format($appointment->start_date, config('settings.defaultTimestampFormat'))]);
            }
        });

        self::deleted(function ($appointment) {
            try {
                $translations = TranslationHelper::getTranslations($appointment->patient->language_id);
                self::notification($appointment, $translations['appointment.deleted_appointment_with'] . ' ' . $appointment->patient->first_name . ' ' . $appointment->patient->last_name);
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            }

            if ($appointment->assistiveTechnology) {
                $assistiveTechnology = AssistiveTechnology::where('appointment_id', $appointment->id)->first();
                $assistiveTechnology->update(['follow_up_date' => null, 'appointment_id' => null]);
            }
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function assistiveTechnology()
    {
        return $this->belongsTo(AssistiveTechnology::class, 'id', 'appointment_id');
    }

    /**
     * @param \App\Models\User $user
     * @param \App\Models\Appointment $appointment
     * @param string $heading
     *
     * @return bool
     */
    public static function notification($appointment, $heading)
    {
        if ($appointment->patient) {
            $token = $appointment->patient->firebase_token;
            $body = $appointment->start_date . '|' . $appointment->end_date;

            event(new PodcastNotificationEvent($token, null, null, $heading, $body, true));
        }
        return true;
    }
}

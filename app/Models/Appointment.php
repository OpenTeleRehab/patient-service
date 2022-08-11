<?php

namespace App\Models;

use App\Helpers\TranslationHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Events\PodcastNotificationEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class Appointment extends Model
{
    const STATUS_INVITED = 'invited';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REJECTED = 'rejected';

    use HasFactory;

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
    ];

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
                $user = User::find($appointment->patient_id);
                $translations = TranslationHelper::getTranslations($user->language_id);

                self::notification($user, $appointment, $translations['appointment.invitation_appointment_with']);
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            }
        });

        self::updated(function ($appointment) {
            try {
                $user = User::find($appointment->patient_id);
                $translations = TranslationHelper::getTranslations($user->language_id);

                self::notification($user, $appointment, $translations['appointment.updated_appointment_with']);
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            }
        });

        self::deleted(function ($appointment) {
            try {
                $user = User::find($appointment->patient_id);
                $translations = TranslationHelper::getTranslations($user->language_id);

                self::notification($user, $appointment, $translations['appointment.deleted_appointment_with']);
            } catch (\Exception $e) {
                Log::error($e->getMessage());
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
     * @param object $user
     * @param object $appointment
     * @param string $head
     *
     * @return bool
     */
    static function notification($user, $appointment, $heading)
    {
        if ($user) {
            $token = $user->firebase_token;
            $title = $heading . ' ' . $user->first_name . ' ' . $user->last_name;
            $body = Carbon::parse($appointment->start_date)->format('d/m/Y h:i A') . ' - ' . Carbon::parse($appointment->end_date)->format('d/m/Y h:i A');

            event(new PodcastNotificationEvent($token, null, null, $title, $body));
        }
        return true;
    }
}

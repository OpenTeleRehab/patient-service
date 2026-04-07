<?php

namespace App\Models;

use App\Events\AppointmentEvent;
use App\Helpers\TranslationHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity as ActivityLog;
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
        'type',
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
            ->logExcept(['id', 'created_at', 'updated_at', 'created_by_therapist', 'patient_id', 'therapist_id']);
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
                self::notification($appointment, 'invitation');
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
     * Get healthcare worker devices
     *
     * @param $id
     * @return array|mixed
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function phcWorker($id)
    {
        $accessToken = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);

        $userResponse = Http::withToken($accessToken)->get(env('THERAPIST_SERVICE_URL') . '/phc-workers/by-id', [
            'id' => $id
        ]);

        if ($userResponse->successful()) {
            $userResponse = $userResponse->json();
            return $userResponse['data'];
        }

        return null;
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
     * @param string $title
     *
     * @return bool
     */
    public static function notification($appointment, $status)
    {
        if (in_array($status, ['accepted', 'rejected'])) {
            if ($appointment->created_by_therapist) {
                $user = $appointment->phcWorker($appointment->therapist_id);
                $patient = $appointment->patient;
                $participantName = sprintf('%s %s', $patient->last_name, $patient->first_name);
            } else {
                $user = $appointment->patient;
                $phcWorker = $appointment->phcWorker($appointment->therapist_id);
                $participantName = sprintf('%s %s', $phcWorker['last_name'], $phcWorker['first_name']);
            }

            $translations = TranslationHelper::getTranslations($user['language_id']);

            $title = $translations['appointment.updated_appointment_with'] ?? '';
            $title .= sprintf(' %s', $participantName);
            $title .= sprintf(' %s', $translations['appointment.invitation.' . $status] ?? '');
        } else {
            if ($appointment->created_by_therapist) {
                $user = $appointment->patient;
                $phcWorker = $appointment->phcWorker($appointment->therapist_id);
                $participantName = sprintf('%s %s', $phcWorker['last_name'], $phcWorker['first_name']);
            } else {
                $user = $appointment->phcWorker($appointment->therapist_id);
                $patient = $appointment->patient;
                $participantName = sprintf('%s %s', $patient->last_name, $patient->first_name);
            }

            $translations = TranslationHelper::getTranslations($user['language_id']);

            $title = $translations['appointment.' . $status . '_appointment_with'] ?? '';
            $title .= sprintf(' %s', $participantName);
        }

        if (isset($user['devices'])) {
            foreach ($user['devices'] as $device) {
                $fcmToken = $device['fcm_token'];
                $fcmToken && event(new AppointmentEvent($fcmToken, $title, $appointment->start_date, $appointment->end_date));
            }
        }

        if (isset($user['firebase_token'])) {
            $fcmToken = $user['firebase_token'];
            event(new AppointmentEvent($fcmToken, $title, $appointment->start_date, $appointment->end_date));
        }

        return true;
    }
}

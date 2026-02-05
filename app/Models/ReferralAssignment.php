<?php

namespace App\Models;

use App\Mail\PatientReferralMail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use App\Helpers\UserHelper;
use Spatie\Activitylog\Models\Activity as ActivityLog;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class ReferralAssignment extends Model
{
    use LogsActivity;

    const STATUS_INVITED = 'invited';
    const STATUS_DECLINED = 'declined';
    const STATUS_ACCEPTED = 'accepted';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'referral_id',
        'therapist_id',
        'accepted_by',
        'status',
        'reason',
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
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        self::created(function ($assignment) {
            $therapistAccessToken = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);
            $adminAccessToken = Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE);

            $therapist = Http::withToken($therapistAccessToken)->get(env('THERAPIST_SERVICE_URL') . '/therapist/by-id', [
                'id' => $assignment->therapist_id,
            ])->throw();

            $rehabServiceAdmin = Http::withToken($adminAccessToken)
                ->get(env('ADMIN_SERVICE_URL') . '/internal/user/' . $assignment->accepted_by)
                ->throw();

            if ($therapist->successful()) {
                $therapist = $therapist->json();

                if ($therapist['notify_email']) {
                    Mail::to($therapist['email'])->send(
                        new PatientReferralMail(
                            'new-assigned-patient-referral-request-from-a-rehab-service-admin',
                            UserHelper::getFullName($therapist['last_name'], $therapist['first_name'], $therapist['language_id']),
                            UserHelper::getFullName($rehabServiceAdmin['data']['last_name'], $rehabServiceAdmin['data']['first_name'], $therapist['language_id']),
                             $therapist['language_id'] ?? null,
                            $therapist['language_id'] ?? null,
                        )
                    );
                }
            }
        });

        self::updated(function ($assignment) {
            $therapistAccessToken = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);
            $adminAccessToken = Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE);

            $healthcareWorker = Http::withToken($therapistAccessToken)->get(env('THERAPIST_SERVICE_URL') . '/therapist/by-id', [
                'id' => $assignment->referral->phc_worker_id,
            ])->throw();

            $therapist = Http::withToken($therapistAccessToken)->get(env('THERAPIST_SERVICE_URL') . '/therapist/by-id', [
                'id' => $assignment->therapist_id,
            ])->throw();

            if ($healthcareWorker->successful() && $therapist->successful()) {
                $healthcareWorker = $healthcareWorker->json();

                if ($healthcareWorker['notify_email'] && $assignment->status === self::STATUS_ACCEPTED) {
                    $prefix = 'therapist-accepts-the-assigned-patient-referral-request-for-healthcare-worker';

                    Mail::to($healthcareWorker['email'])->send(
                        new PatientReferralMail(
                            $prefix,
                            UserHelper::getFullName($healthcareWorker['last_name'], $healthcareWorker['first_name'], $healthcareWorker['language_id']),
                            UserHelper::getFullName($therapist['last_name'], $therapist['first_name'], $healthcareWorker['language_id']),
                            $healthcareWorker['language_id'] ?? null,
                        )
                    );
                }
            }

            // Send notification to rehab service admin.
            Http::withToken($adminAccessToken)->post(env('ADMIN_SERVICE_URL') . '/notifications/patient-referral-assignment', [
                'clinic_id' => $assignment->referral->to_clinic_id,
                'therapist_id' => $assignment->therapist_id,
                'status' => $assignment->status,
            ])->throw();
        });
    }

    /**
     * Get the referral associated with this referral assignment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function referral()
    {
        return $this->belongsTo(Referral::class);
    }

}

<?php

namespace App\Models;

use App\Mail\PatientReferralMail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class ReferralAssignment extends Model
{
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

            $healthcareWorker = Http::withToken($therapistAccessToken)->get(env('THERAPIST_SERVICE_URL') . '/therapist/by-id', [
                'id' => $assignment->referral->phc_worker_id,
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
                            $therapist['last_name'] . ' ' . $therapist['first_name'],
                            $rehabServiceAdmin['data']['last_name'] . ' ' . $rehabServiceAdmin['data']['first_name'],
                            $therapist['language_id'],
                        )
                    );
                }
            }

            if ($healthcareWorker->successful() && $rehabServiceAdmin['data']) {
                $healthcareWorker = $healthcareWorker->json();

                if ($healthcareWorker['notify_email']) {
                    Mail::to($healthcareWorker['email'])->send(
                        new PatientReferralMail(
                            'rehab-service-admin-assigns-the-patient-referral-request',
                            $healthcareWorker['last_name'] . ' ' . $healthcareWorker['first_name'],
                            $rehabServiceAdmin['data']['last_name'] . ' ' . $rehabServiceAdmin['data']['first_name'],
                            $healthcareWorker['language_id'],
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

                if ($healthcareWorker['notify_email']) {
                    $prefix = $assignment === self::STATUS_ACCEPTED
                        ? 'therapist-accepts-the-assigned-patient-referral-request-for-healthcare-worker'
                        : 'therapist-declines-the-assigned-patient-referral-request-for-healthcare-worker';

                    Mail::to($healthcareWorker['email'])->send(
                        new PatientReferralMail(
                            $prefix,
                            $healthcareWorker['last_name'] . ' ' . $healthcareWorker['first_name'],
                            $therapist['last_name'] . ' ' . $therapist['first_name'],
                            $healthcareWorker['language_id'],
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

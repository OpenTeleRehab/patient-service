<?php

namespace App\Models;

use App\Mail\PatientReferralMail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class Referral extends Model
{
    const STATUS_INVITED = 'invited';
    const STATUS_DECLINED = 'declined';
    const STATUS_ACCEPTED = 'accepted';
    const LEAD_PHC_WORKER = 'lead';
    const SUPPLEMENTARY_PHC_WORKER = 'supplementary';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'patient_id',
        'phc_worker_id',
        'to_region_id',
        'to_clinic_id',
        'status',
        'request_reason',
        'reject_reason'
    ];

    /**
     * Get the patient associated with this referral.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    /**
     * Get all referral assignments for this referral.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function referralAssignments()
    {
        return $this->hasMany(ReferralAssignment::class);
    }

    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        self::creating(function ($referral) {
            $authUser = Auth::user();
            $referral->status = self::STATUS_INVITED;

            if ($authUser->user_type === User::GROUP_PHC_WORKER && $authUser->therapist_user_id !== null) {
                $referral->phc_worker_id = $authUser->therapist_user_id;
            }
        });

        self::creating(function ($referral) {
            // Send notification to rehab service admin.
            Http::withToken(Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE))->post(env('ADMIN_SERVICE_URL') . '/notifications/patient-referral', [
                'clinic_id' => $referral->to_clinic_id,
                'region_id' => $referral->to_region_id,
                'phc_worker_id' => $referral->phc_worker_id,
            ])->throw();
        });

        self::updated(function ($referral) {
            if ($referral->status === self::STATUS_DECLINED) {
                $access_token = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);

                $healthcareWorker = Http::withToken($access_token)->get(env('THERAPIST_SERVICE_URL') . '/therapist/by-id', [
                    'id' => $referral->phc_worker_id,
                ])->throw();

                // TODO: Fetch rehab service admin name.
                if ($healthcareWorker->successful()) {
                    $healthcareWorker = $healthcareWorker->json();

                    Mail::to($healthcareWorker['email'])->send(
                        new PatientReferralMail(
                            'rehab-service-admin-declines-the-patient-referral-request',
                            $healthcareWorker['last_name'] . ' ' . $healthcareWorker['first_name'],
                            '[Rehab Service Admin Name]',
                            $healthcareWorker['language_id'],
                        )
                    );
                }
            }
        });
    }
}

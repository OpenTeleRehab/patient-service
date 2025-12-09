<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Referral extends Model
{
    const STATUS_INVITED = 'invited';
    const STATUS_DECLINED = 'declined';
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
        'to_clinic_id',
        'status',
        'reason',
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
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralAssignment extends Model
{
    const STATUS_INVITED = 'invited';
    const STATUS_DECLINED = 'declined';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'referral_id',
        'therapist_id',
        'reason',
    ];

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

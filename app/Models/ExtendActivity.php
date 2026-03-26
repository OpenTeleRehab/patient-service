<?php
namespace App\Models;

use Spatie\Activitylog\Models\Activity;

class ExtendActivity extends Activity
{
    const THERAPIST_SERVICE = 'therapist_service';
    const ADMIN_SERVICE = 'admin_service';
    const PATIENT_SERVICE = 'patient_service';
    const UNKNOWN = 'unknown';

    public function user()
    {
        return $this->belongsTo(User::class, 'causer_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssistiveTechnology extends Model
{
    use SoftDeletes;

    const ASSISTIVE_TECHNOLOGY_FOLLOW_UP = 'AT_FOLLOW_UP';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'assistive_technology_id',
        'patient_id',
        'therapist_id',
        'appointment_id',
        'provision_date',
        'follow_up_date',
    ];

    /**
     * The attributes that should be cast to native types.
     * This format will be used when the model is serialized to an array or JSON
     *
     * @var array
     */
    protected $casts = [
        'provision_date' => 'date:Y-m-d',
        'follow_up_date' => 'date:Y-m-d',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['follow_up_date'];

    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        self::updated(function ($assistive_technology) {
            $followUpDate = $assistive_technology->follow_up_date->format('Y-m-d');

            Appointment::where('id', $assistive_technology->appointment_id)->update([
                'start_date' => date_create_from_format('Y-m-d H:i:s', $followUpDate . ' ' . '01:00:00'),
                'end_date' => date_create_from_format('Y-m-d H:i:s', $followUpDate . ' ' . '10:00:00')
            ]);
        });
    }
}

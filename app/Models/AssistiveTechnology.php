<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssistiveTechnology extends Model
{
    use SoftDeletes;

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
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function appointment()
    {
        return $this->hasOne(Appointment::class, 'id', 'appointment_id');
    }

    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        self::deleted(function ($assistive_technology) {
            Appointment::where('id', $assistive_technology->appointment_id)->delete();
        });
    }
}

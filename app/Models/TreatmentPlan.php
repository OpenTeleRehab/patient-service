<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class TreatmentPlan extends Model
{
    const STATUS_PLANNED = 'planned';
    const STATUS_ON_GOING = 'on_going';
    const STATUS_FINISHED = 'finished';
    const FILTER_STATUS_FINISHED = 1;
    const FILTER_STATUS_PLANNED = 2;
    const FILTER_STATUS_ON_GOING = 3;
    const PAIN_THRESHOLD_LIMIT = 'pain_threshold_limit';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
        'patient_id',
        'start_date',
        'end_date',
        'status',
        'total_of_weeks',
        'created_by',
        'disease_id',
    ];

    /**
     * The attributes that should be cast to native types.
     * This format will be used when the model is serialized to an array or JSON
     *
     * @var array
     */
    protected $casts = [
        'start_date' => 'datetime:d/m/Y',
        'end_date' => 'datetime:d/m/Y',
    ];

    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // Set default order.
        static::addGlobalScope('order', function (Builder $builder) {
            $builder->orderBy('start_date', 'desc');
            $builder->orderBy('name');
        });

        self::deleting(function ($treatment) {
            try {
                Goal::where('treatment_plan_id', $treatment->id)->delete();
                Activity::where('treatment_plan_id', $treatment->id)->delete();
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            }
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function goals()
    {
        return $this->hasMany(Goal::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function activities()
    {
        return $this->hasMany(Activity::class);
    }
}

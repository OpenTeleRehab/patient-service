<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class TreatmentPlan extends Model
{
    const TYPE_PRESET = 'preset';
    const TYPE_NORMAL = 'normal';
    const STATUS_PLANNED = 'planned';
    const STATUS_ON_GOING = 'on-going';
    const STATUS_FINISHED = 'finished';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'description', 'type', 'patient_id', 'start_date', 'end_date', 'status',
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
        $orderValues = [self::STATUS_ON_GOING, self::STATUS_PLANNED, self::STATUS_FINISHED];

        // Set default order.
        static::addGlobalScope('order', function (Builder $builder) use ($orderValues) {
            $builder->orderByRaw('FIELD(status, "' . implode('", "', $orderValues) . '")');
            $builder->orderBy('name', 'asc');
            $builder->orderBy('start_date', 'asc');
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function activities()
    {
        return $this->hasMany(Activity::class);
    }
}

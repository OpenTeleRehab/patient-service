<?php

namespace App\Models;

use App\Helpers\RocketChatHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    const ADMIN_GROUP_GLOBAL_ADMIN = 'global_admin';
    const ADMIN_GROUP_COUNTRY_ADMIN = 'country_admin';
    const ADMIN_GROUP_CLINIC_ADMIN = 'clinic_admin';

    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'gender',
        'date_of_birth',
        'note',
        'country_id',
        'clinic_id',
        'phone',
        'identity',
        'therapist_id',
        'enabled',
        'password',
        'otp_code',
        'language_id',
        'term_and_condition_id',
        'chat_user_id',
        'chat_password',
        'chat_rooms',
        'last_login',
        'secondary_therapists',
        'created_by',
        'dial_code',
        'privacy_and_policy_id',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'chat_rooms' => 'array',
        'secondary_therapists' => 'array',
    ];

    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // Set default order by status (active/inactive), last name, and first name.
        static::addGlobalScope('order', function (Builder $builder) {
            // Add treatment status order here.
            $builder->orderBy('last_name');
            $builder->orderBy('first_name');
        });

        self::updated(function ($user) {
            try {
                RocketChatHelper::updateUser($user->chat_user_id, [
                    'active' => boolval($user->enabled),
                    'name' => $user->last_name . ' ' . $user->first_name
                ]);
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            }
        });

        self::deleting(function ($user) {
            try {
                RocketChatHelper::deleteUser($user->chat_user_id);
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            }
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function treatmentPlans()
    {
        return $this->hasMany(TreatmentPlan::class, 'patient_id', 'id');
    }
}

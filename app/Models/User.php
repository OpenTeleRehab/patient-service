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
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    const ADMIN_GROUP_GLOBAL_ADMIN = 'global_admin';
    const ADMIN_GROUP_COUNTRY_ADMIN = 'country_admin';
    const ADMIN_GROUP_CLINIC_ADMIN = 'clinic_admin';
    const FINISHED_TREATMENT_PLAN = 1;
    const PLANNED_TREATMENT_PLAN = 2;
    const SECONDARY_TERAPIST = 2;

    const BRONZE_DAILY_LOGINS = 4;
    const SILVER_DAILY_LOGINS = 8;
    const GOLD_DAILY_LOGINS = 12;
    const DIAMOND_DAILY_LOGINS = 16;

    const BRONZE_DAILY_TASKS = 5;
    const SILVER_DAILY_TASKS = 15;
    const GOLD_DAILY_TASKS = 25;
    const DIAMOND_DAILY_TASKS = 35;

    const BRONZE_DAILY_ANSWERS = 2;
    const SILVER_DAILY_ANSWERS = 5;
    const GOLD_DAILY_ANSWERS = 8;
    const DIAMOND_DAILY_ANSWERS = 11;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'email',
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
        'completed_percent',
        'total_pain_threshold',
        'kid_theme',
        'init_daily_tasks',
        'init_daily_logins',
        'init_daily_answers',
        'daily_tasks',
        'daily_logins',
        'daily_answers',
        'firebase_token',
        'last_reminder',
        'location',
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
        'last_reminder' => 'datetime',
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

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'patient_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function assistiveTechnologies()
    {
        return $this->hasMany(AssistiveTechnology::class, 'patient_id', 'id');
    }

    
    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function callHistories()
    {
        return $this->hasMany(CallHistory::class, 'patient_id', 'id');
    }

    /**
     * Get the user's full name.
     *
     * @return string
     */
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }
}

<?php

namespace App\Console\Commands;

use App\Events\PodcastNotificationEvent;
use App\Helpers\TranslationHelper;
use App\Models\Appointment;
use App\Models\TreatmentPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DailyReminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:daily-reminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Daily reminder notification';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
       User::each(function ($user) {
           $upCommingAppointments = Appointment::where('therapist_status', '<>', Appointment::STATUS_ACCEPTED)
               ->where('patient_status', '<>', Appointment::STATUS_ACCEPTED)
               ->whereDate('end_date', Carbon::now())
               ->where('patient_id', $user->id)
               ->count();
           $ongoingTreatmentPlan =  TreatmentPlan::whereDate('start_date', '<=', Carbon::now())
               ->whereDate('end_date', '>=', Carbon::now())
               ->where('patient_id', $user->id)
               ->first();
           if ($ongoingTreatmentPlan) {
               $activities = array_filter($ongoingTreatmentPlan->activities->toArray(), function ($activity){
                   return $activity['completed'] == 0;
               });

               if (count($activities) > 0) {
                   $translations = TranslationHelper::getTranslations($user->language_id);
                   $token = $user->firebase_token;
                   $title = $translations['common.activity'];
                   $body = $translations['activity.daily_reminder'] . ' ' . count($activities) . ' ' . $translations['common.activities'] ;
                   event(new PodcastNotificationEvent($token, null, null, $title, $body));
               }
           }

           if ($upCommingAppointments > 0) {
               $translations = TranslationHelper::getTranslations($user->language_id);
               $token = $user->firebase_token;
               $title = $translations['appointment'];
               $body = $translations['appointment.daily_reminder'] . ' ' . $upCommingAppointments . ' ' . $translations['appointments'] ;
               event(new PodcastNotificationEvent($token, null, null, $title, $body));
           }
       });
        return true;
    }
}

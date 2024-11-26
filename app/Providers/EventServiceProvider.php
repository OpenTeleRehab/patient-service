<?php

namespace App\Providers;

use App\Events\AddLogToAdminServiceEvent;
use App\Events\LoginEvent;
use App\Events\PodcastCalculatorEvent;
use App\Events\PodcastNotificationEvent;
use App\Listeners\AddLogToAdminServiceListener;
use App\Listeners\Calculator;
use App\Listeners\InitBadge;
use App\Listeners\SendPodcastNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        LoginEvent::class => [
            InitBadge::class,
        ],
        PodcastCalculatorEvent::class => [
            Calculator::class
        ],
        PodcastNotificationEvent::class => [
            SendPodcastNotification::class
        ],
        AddLogToAdminServiceEvent::class => [
            AddLogToAdminServiceListener::class
        ]
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}

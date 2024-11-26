<?php

namespace App\Listeners;

use App\Events\AddLogToAdminServiceEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Helpers\AuditLogHelper;

class AddLogToAdminServiceListener
{
    /**
     * Handle the event.
     *
     * @param AddLogToAdminServiceEvent $event
     *
     * @return void
     */
    public function handle(AddLogToAdminServiceEvent $event)
    {
        $response = AuditLogHelper::store($event->activityLogger, $event->user);
    }
}

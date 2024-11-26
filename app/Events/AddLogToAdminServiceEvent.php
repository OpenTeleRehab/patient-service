<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Spatie\Activitylog\Models\Activity;
use App\Models\User;

class AddLogToAdminServiceEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var Activity $activityLogger
     */
    public $activityLogger;

    /**
     * @var User $user
     */
    public $user;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Activity $activityLogger, User $user)
    {
        $this->activityLogger = $activityLogger;
        $this->user = $user;
    }
}

<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppointmentEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var string
     */
    public $fcmToken;

    /**
     * @var string
     */
    public $title;

    /**
     * @var string
     */
    public $startDate;

    /**
     * @var string
     */
    public $endDate;

    /**
     * Create a new event instance.
     *
     * @param string $fcmToken
     * @param string $title
     * @param string $startDate
     * @param string $endDate
     *
     * @return void
     */
    public function __construct($fcmToken, $title, $startDate, $endDate)
    {
        $this->fcmToken = $fcmToken;
        $this->title = $title;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}

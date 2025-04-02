<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PodcastNotificationEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var string
     */
    public $token;

    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $rid;

    /**
     * @var string
     */
    public $title;

    /**
     * @var string
     */
    public $body;

    /**
     * @var boolean
     */
    public $isLocal;

    /**
     * Create a new event instance.
     *
     * @param string $token
     * @param string $id
     * @param string $rid
     * @param string $title
     * @param string $body
     * @param boolean $isLocal
     *
     * @return void
     */
    public function __construct($token, $id, $rid, $title, $body, $isLocal = false)
    {
        $this->token = $token;
        $this->id = $id;
        $this->rid = $rid;
        $this->title = $title;
        $this->body = $body;
        $this->isLocal = $isLocal;
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

<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TreatmentPlanEvent
{
    use Dispatchable, SerializesModels;

    const ACTION_CREATED = 'created';
    const ACTION_UPDATED = 'updated';

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
    public $body;

    /**
     * @var integer
     */
    public $treatmentPlanId;

    /**
     * @var string
     */
    public $action;

    /**
     * Create a new event instance.
     *
     * @param string $fcmToken
     * @param string $title
     * @param string $body
     * @param integer $treatmentPlanId
     * @param string $action
     *
     * @return void
     */
    public function __construct($fcmToken, $title, $body, $treatmentPlanId, $action)
    {
        $this->fcmToken = $fcmToken;
        $this->title = $title;
        $this->body = $body;
        $this->treatmentPlanId = $treatmentPlanId;
        $this->action = $action;
    }
}

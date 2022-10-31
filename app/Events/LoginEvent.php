<?php

namespace App\Events;

class LoginEvent
{
    /**
     * @var array
     */
    public $request;

    /**
     * Create a new event instance.
     *
     * @param object $request
     *
     * @return void
     */
    public function __construct($request)
    {
        $this->request = $request;
    }
}

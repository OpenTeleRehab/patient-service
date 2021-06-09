<?php

namespace App\Console\Commands;

use App\Helpers\RocketChatHelper;
use App\Models\User;
use Illuminate\Console\Command;

class RemoveChat extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:chat-cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove chat/call history - 1 year';

    /**
     * @return void
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function handle()
    {
        User::where('enabled', true)->each(function ($user) {
            if ($user->chat_rooms) {
                foreach ($user->chat_rooms as $chat_room) {
                    RocketChatHelper::deleteMessages($chat_room);
                }
            }
        });
        $this->info('Chat has been remove successfully');
    }
}

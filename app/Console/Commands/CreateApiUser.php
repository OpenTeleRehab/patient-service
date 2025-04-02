<?php

namespace App\Console\Commands;

use App\Models\ApiUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateApiUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:create-api-user {email} {first_name} {last_name} {password}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create api user';

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
        $email = $this->argument('email');
        $firstName = $this->argument('first_name');
        $lastName = $this->argument('last_name');
        $password = $this->argument('password');

        ApiUser::updateOrCreate(
            [
                'email' => $email,
            ],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'password' => Hash::make($password),
            ]
        );

        $this->info('Api user has been created successfully');
        return true;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateBackendUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:create-backend-user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create backend user';

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
        User::updateOrCreate([
            'email' => env('KEYCLOAK_BACKEND_CLIENT'),
        ], [
            'email' => env('KEYCLOAK_BACKEND_CLIENT'),
            'password' => Hash::make(env('PATIENT_BACKEND_PIN')),
            'first_name' => 'DO NOT DELETE!',
            'last_name' => 'DO NOT DELETE!',
            'gender' => '',
            'phone' => '',
            'clinic_id' => 0,
            'country_id' => 0,
            'therapist_id' => 0,
            'enabled' => 1,
        ]);

        $this->info('Backend user has been created successfully');
        return true;
    }
}

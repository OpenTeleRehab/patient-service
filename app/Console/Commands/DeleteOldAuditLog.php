<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

class DeleteOldAuditLog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:clean-up-old-audit-log';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete old audit logs equal or older than 2 years';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Activity::where('created_at', '<=', now()->subYears(2))->delete();
        $this->info('Old audit logs deleted successfully!');
        return Command::SUCCESS;
    }
}

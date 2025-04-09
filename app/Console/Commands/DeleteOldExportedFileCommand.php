<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class DeleteOldExportedFileCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:clean-up-exported-files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete files older than 7 days from storage/app/exports';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();

        // Get all files in the storage/app/exports directory
        $files = Storage::files('exports');

        foreach ($files as $file) {
            $lastModified = Carbon::createFromTimestamp(Storage::lastModified($file));

            // Check if the file is older than 7 days
            if ($lastModified->diffInDays($now) >= 7) {
                Storage::delete($file);
                $this->info("Deleted: {$file}");
            }
        }

        $this->info('Old files deleted successfully!');
        return Command::SUCCESS;
    }
}

<?php

namespace App\Jobs;

use App\Enums\ExportStatus;
use App\Exports\QuestionnaireResultExport;
use App\Models\Forwarder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class GenerateExport implements ShouldQueue
{

    const TYPE_QUESTIONNAIRE_RESULT = 'questionnaire_result';

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;

    /** @var $payload */
    protected $payload;

    /**
     * GenerateExport constructor.
     *
     * @param $payload
     */
    public function __construct($payload)
    {
        $this->payload = $payload;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $type = $this->payload['type'];
        if ($type === self::TYPE_QUESTIONNAIRE_RESULT) {
            $filePath = QuestionnaireResultExport::export($this->payload);
        }

        $status = isset($filePath) ? ExportStatus::SUCCESS->value : ExportStatus::FAILED->value;
        self::updateDownloadTracker($status, $filePath ?? null);
    }

    /**
     *  The job failed to process.
     *
     * @param $exception
     *
     * @return void
     */
    public function failed($exception)
    {
        self::updateDownloadTracker(ExportStatus::FAILED->value, $filePath ?? null);
    }

    /**
     * Function to update download tracker
     *
     * @param $status
     * @param null $filePath
     * @return void
     * @throws ConnectionException
     */
    private function updateDownloadTracker($status, $filePath = null) {
        $jobId = $this->payload['job_id'];
        $source = $this->payload['source'];
        $data = [
            'job_id' => $jobId,
            'status' => $status,
            'file_path' => $filePath,
        ];

        if ($source === Forwarder::THERAPIST_SERVICE) {
            $access_token = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);
            Http::withToken($access_token)->put(env('THERAPIST_SERVICE_URL') . '/download-trackers', $data);
        }
    }
}

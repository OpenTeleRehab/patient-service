<?php

namespace App\Mail;

use App\Models\Forwarder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class PatientReferralMail extends Mailable
{
    use Queueable, SerializesModels;

    public $subject;
    public $content;

    /**
     * Create a new message instance.
     */
    public function __construct(string $prefix, string $recipientName, string $actionName, ?int $languageId = null)
    {
        $endpoint = env('ADMIN_SERVICE_URL') . '/email-templates/'. $prefix .'/get-by-prefix';
        $accessToken = Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE);

        $template = Http::withToken($accessToken)->get($endpoint, [
            'lang' => $languageId,
        ]);

        if ($template->successful()) {
            $template = $template->json()['data'];

            $this->subject = $template['title'];
            $this->content = $template['content'];

            // Replace email content.
            $this->content = str_replace('#user_name#', $recipientName, $this->content);
            $this->content = str_replace('#healthcare_worker_name#', $actionName, $this->content);
            $this->content = str_replace('#rehab_service_admin_name#', $actionName, $this->content);
            $this->content = str_replace('#therapist_name#', $actionName, $this->content);
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.referral',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}

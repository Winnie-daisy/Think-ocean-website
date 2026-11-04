<?php

namespace App\Notifications;

use App\Filament\Resources\ContactSubmissions\ContactSubmissionResource;
use App\Models\ContactSubmission;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells staff a message has arrived. The message itself is not included:
 * staff read it in the CMS, so personal data is not spread across inboxes.
 */
class NewContactSubmission extends Notification
{
    public function __construct(private readonly ContactSubmission $submission) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New message through the Think Ocean website')
            ->line('A new message has been received through the website contact form.')
            ->action('Read it in the CMS', ContactSubmissionResource::getUrl('view', ['record' => $this->submission]))
            ->line('Please do not forward personal details outside the CMS unless necessary.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Models\ContactSubmission;
use App\Models\Page;
use App\Notifications\NewContactSubmission;
use App\Services\ActivityLogger;
use App\Support\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class ContactController extends Controller
{
    public function show(SiteSettings $settings): View
    {
        return view('contact', [
            'page' => Page::query()->published()->where('slug', 'contact')->first(),
            'formEnabled' => (bool) $settings->get('contact_form_enabled', true),
            'formStarted' => Crypt::encryptString((string) now()->timestamp),
        ]);
    }

    public function store(ContactRequest $request, SiteSettings $settings, ActivityLogger $activity): RedirectResponse
    {
        abort_unless($settings->get('contact_form_enabled', true), 404);

        $success = $request->validated('source') === 'get-involved'
            ? redirect()->to(route('get-involved').'#help')->with('enquiry_status', 'Thank you for getting in touch. We will be in contact soon.')
            : redirect()->route('contact')->with('status', 'Thank you for your message. We will reply as soon as we can.');

        // Respond identically to bots so they learn nothing, but store nothing.
        if ($request->isLikelySpam()) {
            $activity->log('contact.spam_blocked', 'Blocked a likely spam contact submission', null, []);

            return $success;
        }

        $submission = new ContactSubmission($request->safe()->only(['name', 'email', 'organisation', 'subject', 'message']));
        $submission->message ??= 'Get Involved enquiry: interested in '.($submission->subject ?: 'helping').'.';
        $submission->forceFill([
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ])->save();

        $recipient = $settings->get('contact_notification_email');

        if (filled($recipient)) {
            try {
                Notification::route('mail', $recipient)->notify(new NewContactSubmission($submission));
            } catch (Throwable $e) {
                // The message is safely stored; a mail failure must not lose it or show an error.
                Log::warning('Contact notification could not be sent', ['submission' => $submission->getKey(), 'error' => $e->getMessage()]);
            }
        }

        return $success;
    }
}

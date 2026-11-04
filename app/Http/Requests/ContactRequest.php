<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class ContactRequest extends FormRequest
{
    /** Minimum seconds between rendering the form and submitting it. Faster submissions are almost always bots. */
    public const MIN_SECONDS = 3;

    /** Pages other than Contact that post to this form, and return there afterwards. */
    public const SOURCES = ['get-involved'];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            'organisation' => ['nullable', 'string', 'max:150'],
            'subject' => ['nullable', 'string', 'max:150'],
            // The short "Tell us how you'd like to help" form on Get Involved sends no message.
            'source' => ['nullable', 'in:'.implode(',', self::SOURCES)],
            'message' => ['required_unless:source,get-involved', 'nullable', 'string', 'min:10', 'max:5000'],
            'consent' => ['accepted'],
            // Spam traps, checked in isLikelySpam().
            'website' => ['nullable', 'string', 'max:255'],
            'form_started' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Please enter your name.',
            'email.required' => 'Please enter your email address so we can reply.',
            'email.email' => 'Please enter a valid email address, like name@example.com.',
            'message.required' => 'Please enter a message.',
            'message.required_unless' => 'Please enter a message.',
            'message.min' => 'Your message is a little short. Please add a few more details.',
            'consent.accepted' => 'Please confirm that we may use your details to respond to your message.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Normalise whitespace and strip control characters from single-line fields.
        foreach (['name', 'email', 'organisation', 'subject'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $this->input($field)))]);
            }
        }

        if (is_string($this->input('message'))) {
            $this->merge(['message' => trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $this->input('message')))]);
        }
    }

    public function isLikelySpam(): bool
    {
        if (filled($this->input('website'))) {
            return true;
        }

        try {
            $started = (int) Crypt::decryptString((string) $this->input('form_started'));
        } catch (Throwable) {
            return true;
        }

        $elapsed = now()->timestamp - $started;

        return $elapsed < self::MIN_SECONDS || $elapsed > 86400;
    }
}

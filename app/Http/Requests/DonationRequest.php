<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DonationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $min = config('services.stripe.min_amount') / 100;
        $max = config('services.stripe.max_amount') / 100;

        return [
            'amount' => ['required', 'numeric', 'min:'.$min, 'max:'.$max, 'decimal:0,2'],
            'frequency' => ['required', 'in:one_off,monthly'],
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'amount.required' => 'Please choose or enter an amount.',
            'amount.min' => 'The smallest donation we can accept online is £:min.',
            'amount.max' => 'For gifts over £:max, please contact us directly.',
            'amount.decimal' => 'Please enter the amount in pounds and pence, like 25 or 25.50.',
            'email.required' => 'Please enter your email address for your receipt.',
            'email.email' => 'Please enter a valid email address, like name@example.com.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // A custom amount, when given, replaces the preset choice.
        $custom = trim((string) $this->input('custom_amount'));

        $this->merge([
            'amount' => $custom !== '' ? str_replace([',', '£', ' '], '', $custom) : $this->input('amount'),
            'name' => is_string($this->input('name')) ? trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $this->input('name'))) : null,
        ]);
    }

    public function isLikelySpam(): bool
    {
        return filled($this->input('website'));
    }

    /** Amount in pence. */
    public function amountInMinorUnits(): int
    {
        return (int) round(((float) $this->validated('amount')) * 100);
    }
}

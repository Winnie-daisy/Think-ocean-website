<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** "Stay connected" sign-ups from the footer. */
class SubscriberController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $back = redirect()->to(url()->previous(route('home')).'#stay-connected');

        // Bots fill in the hidden field; respond as if it worked.
        if (filled($request->input('website'))) {
            return $back->with('subscribed', 'Thank you — you are signed up for updates.');
        }

        $data = $request->validateWithBag('subscribe', [
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
        ], [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
        ]);

        $subscriber = Subscriber::query()->firstOrNew(['email' => mb_strtolower(trim($data['email']))]);

        if (! $subscriber->exists) {
            $subscriber->forceFill(['source' => 'footer', 'ip_address' => $request->ip()])->save();
        }

        return $back->with('subscribed', 'Thank you — you are signed up for updates.');
    }
}

@php
    $site = app(\App\Support\SiteSettings::class);
    $email = $site->get('contact_email');
    $phone = $site->get('contact_phone');
    $address = $site->get('postal_address');
    $fieldOrder = ['name', 'email', 'organisation', 'subject', 'message', 'consent'];
    $subjects = ['General enquiry', 'Partnership', 'Volunteering', 'Donations', 'Media and press', 'Something else'];
    // "Partner With Us" links arrive as /contact?subject=Partnership.
    $selectedSubject = old('subject', in_array(request('subject'), $subjects, true) ? request('subject') : null);
    $social = $site->socialLinks();
    $platforms = \App\Filament\Pages\ManageSettings::SOCIAL_PLATFORMS;
@endphp

<x-layout
    :title="$page?->meta_title ?: ($page?->title ?? 'Contact us')"
    :description="$page?->meta_description ?: $page?->intro"
    :image="$page?->ogImage"
    :canonical="route('contact')"
>
    <x-page-header :title="$page?->title ?? 'Contact us'" :intro="$page?->intro ?? 'We would be glad to hear from you.'" />

    <section class="section">
        <div class="container-site grid items-start gap-14 lg:grid-cols-12">
            <aside class="lg:col-span-5" aria-labelledby="contact-details-heading">
                <h2 id="contact-details-heading" class="section-title">Get in Touch</h2>
                <p class="mt-3 text-slate-muted">Have a question, partnership idea or just want to say hello? We’d love to hear from you.</p>

                @if ($email || $phone || $address)
                    <dl class="mt-8 grid gap-6">
                        @if ($email)
                            <div class="flex items-start gap-4">
                                <x-ocean-icon name="envelope" class="mt-0.5 size-[22px] shrink-0 text-teal-deep" />
                                <div>
                                    <dt class="text-sm font-semibold text-navy">Email</dt>
                                    <dd><a href="mailto:{{ $email }}" class="text-slate-muted hover:text-teal-deep">{{ $email }}</a></dd>
                                </div>
                            </div>
                        @endif
                        @if ($phone)
                            <div class="flex items-start gap-4">
                                <x-ocean-icon name="phone" class="mt-0.5 size-[22px] shrink-0 text-teal-deep" />
                                <div>
                                    <dt class="text-sm font-semibold text-navy">Phone</dt>
                                    <dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="text-slate-muted hover:text-teal-deep">{{ $phone }}</a></dd>
                                </div>
                            </div>
                        @endif
                        @if ($address)
                            <div class="flex items-start gap-4">
                                <x-ocean-icon name="map-pin" class="mt-0.5 size-[22px] shrink-0 text-teal-deep" />
                                <div>
                                    <dt class="text-sm font-semibold text-navy">Location</dt>
                                    <dd class="whitespace-pre-line text-slate-muted">{{ $address }}</dd>
                                </div>
                            </div>
                        @endif
                    </dl>
                @endif

                @if ($social)
                    <ul class="mt-8 flex gap-3" aria-label="Social media">
                        @foreach ($social as $link)
                            <li>
                                <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer me" class="grid size-10 place-items-center rounded-full bg-mist text-teal-deep transition hover:bg-teal-deep hover:text-white">
                                    <x-social-icon :platform="$link['platform']" />
                                    <span class="sr-only">{{ $platforms[$link['platform']] ?? $link['platform'] }} (opens in a new tab)</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </aside>

            <div class="rounded-[18px] border border-line bg-white p-6 shadow-card sm:p-9 lg:col-span-7">
                <h2 class="text-xl font-bold">Send us a message</h2>

                @if (session('status'))
                    <div class="mt-5 flex items-start gap-3 rounded-[10px] bg-[#e7f7ef] px-[18px] py-3.5 text-[#16653a]" role="status" tabindex="-1" id="form-status">
                        <x-ocean-icon name="check-circle" class="size-6 shrink-0" />
                        <p class="font-medium">{{ session('status') }}</p>
                    </div>
                @endif

                @if ($formEnabled)
                    @if ($errors->any())
                        <div class="mt-5 rounded-[10px] border border-red-700/30 bg-red-50 p-5" role="alert" aria-labelledby="error-summary-title" tabindex="-1" id="error-summary">
                            <h3 id="error-summary-title" class="font-sans text-base font-semibold text-red-800">There is a problem with your message</h3>
                            <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-red-800">
                                @foreach ($fieldOrder as $field)
                                    @if ($errors->has($field))
                                        <li><a href="#field-{{ $field }}" class="underline underline-offset-2">{{ $errors->first($field) }}</a></li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('contact.store') }}" class="mt-6 space-y-[18px]" novalidate>
                        @csrf
                        <input type="hidden" name="form_started" value="{{ $formStarted }}">

                        {{-- Honeypot: hidden from people and assistive technology; bots tend to fill it in. --}}
                        <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                            <label for="website">Leave this field empty</label>
                            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                        </div>

                        <x-form-field name="name" label="Name" required autocomplete="name" maxlength="120" />
                        <x-form-field name="email" label="Email" type="email" required autocomplete="email" maxlength="190" />
                        <div class="space-y-2">
                            <label for="field-subject" class="block text-sm font-semibold text-navy">Subject</label>
                            <select id="field-subject" name="subject" class="form-input">
                                <option value="">Select a subject</option>
                                @foreach ($subjects as $subject)
                                    <option value="{{ $subject }}" @selected($selectedSubject === $subject)>{{ $subject }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-form-field name="message" label="Your message" type="textarea" required rows="6" maxlength="5000" />

                        <div class="space-y-2">
                            @error('consent')
                                <p id="field-consent-error" class="flex items-start gap-2 text-sm font-medium text-red-700">
                                    <x-ocean-icon name="exclamation-circle" class="mt-0.5 size-4 shrink-0" />
                                    <span><span class="sr-only">Error:</span> {{ $message }}</span>
                                </p>
                            @enderror
                            <div class="flex items-start gap-3">
                                <input type="checkbox" id="field-consent" name="consent" value="1" required aria-required="true"
                                       @checked(old('consent'))
                                       @error('consent') aria-invalid="true" aria-describedby="field-consent-error" @enderror
                                       class="mt-1 size-5 shrink-0 rounded border-navy/30 text-teal-deep focus:ring-teal">
                                <label for="field-consent" class="text-sm leading-relaxed text-charcoal">
                                    I agree that {{ $site->get('organisation_name') }} may store and use the details I have provided to respond to my message.
                                    @if (\App\Models\Page::slugIsLive('privacy-policy'))
                                        Read our <a href="{{ route('privacy-policy') }}" class="link-arrow">privacy policy</a>.
                                    @endif
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Send Message</button>
                    </form>
                @else
                    <x-empty-state title="Our contact form is currently unavailable" icon="envelope" class="mt-6">
                        @if ($email)
                            Please email us at <a href="mailto:{{ $email }}" class="link-arrow">{{ $email }}</a>.
                        @else
                            Please check back soon.
                        @endif
                    </x-empty-state>
                @endif
            </div>
        </div>
    </section>

    @if ($page?->sections)
        <x-sections :sections="$page->sections" />
    @endif

    @if ($errors->any() || session('status'))
        <x-slot:head>
            <script type="module">
                document.getElementById('{{ $errors->any() ? 'error-summary' : 'form-status' }}')?.focus();
            </script>
        </x-slot:head>
    @endif
</x-layout>

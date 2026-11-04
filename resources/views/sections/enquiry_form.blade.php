@php
    $image = $media->get((int) ($data['image_id'] ?? 0));
    $site = app(\App\Support\SiteSettings::class);
    $options = array_values(array_filter(array_map('trim', explode("\n", (string) ($data['options'] ?? '')))))
        ?: ['Volunteering', 'Partnering with Think Ocean', 'Supporting our work', 'Something else'];
    $isGetInvolved = request()->routeIs('get-involved');
    $hasErrors = $isGetInvolved && $errors->any() && old('source') === 'get-involved';
@endphp

{{-- "Tell us how you'd like to help": a short enquiry over a photograph, stored with contact messages. --}}
@if ($site->get('contact_form_enabled', true))
    <section id="help" class="section scroll-mt-24">
        <div class="container-site">
            <div class="relative isolate overflow-hidden rounded-[18px] bg-navy text-white" data-reveal>
                @if ($image)
                    <x-media-image :media="$image" sizes="100vw" :width="1920" alt="" class="absolute inset-0 -z-20 size-full object-cover" />
                @else
                    <img src="{{ asset('images/think-ocean/news-turtle.jpg') }}" alt="" loading="lazy" class="absolute inset-0 -z-20 size-full object-cover">
                @endif
                <div class="photo-overlay absolute inset-0 -z-10"></div>

                <div class="max-w-md px-6 py-10 sm:px-10">
                    <h2 class="text-2xl font-bold text-white">{{ $data['heading'] ?? 'Tell us how you’d like to help' }}</h2>
                    @if (filled($data['intro'] ?? null))
                        <p class="mt-2 text-sm text-[#e2f3f7]">{{ $data['intro'] }}</p>
                    @endif

                    @if (session('enquiry_status'))
                        <p class="mt-5 rounded-[10px] bg-[#e7f7ef] px-4 py-3 text-sm font-medium text-[#16653a]" role="status">{{ session('enquiry_status') }}</p>
                    @else
                        @if ($hasErrors)
                            <div class="mt-5 rounded-[10px] bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                                <p class="font-semibold">Please check the form</p>
                                <ul class="mt-1 list-disc pl-5">
                                    @foreach ($errors->all() as $message)
                                        <li>{{ $message }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('contact.store') }}" class="mt-5 space-y-3.5" novalidate>
                            @csrf
                            <input type="hidden" name="source" value="get-involved">
                            <input type="hidden" name="form_started" value="{{ \Illuminate\Support\Facades\Crypt::encryptString((string) now()->timestamp) }}">
                            <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                                <label for="help-website">Leave this field empty</label>
                                <input type="text" id="help-website" name="website" tabindex="-1" autocomplete="off">
                            </div>

                            <div>
                                <label for="help-name" class="mb-1 block text-[13px] font-semibold">Name <span aria-hidden="true">*</span></label>
                                <input id="help-name" name="name" required maxlength="120" autocomplete="name" placeholder="Your name" value="{{ $hasErrors ? old('name') : '' }}" class="form-input py-2.5">
                            </div>
                            <div>
                                <label for="help-email" class="mb-1 block text-[13px] font-semibold">Email <span aria-hidden="true">*</span></label>
                                <input id="help-email" type="email" name="email" required maxlength="190" autocomplete="email" placeholder="you@example.com" value="{{ $hasErrors ? old('email') : '' }}" class="form-input py-2.5">
                            </div>
                            <div>
                                <label for="help-subject" class="mb-1 block text-[13px] font-semibold">I’m interested in…</label>
                                <select id="help-subject" name="subject" class="form-input py-2.5">
                                    @foreach ($options as $option)
                                        <option value="{{ $option }}" @selected($hasErrors && old('subject') === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex items-start gap-2.5">
                                <input type="checkbox" id="help-consent" name="consent" value="1" required class="mt-1 size-4 shrink-0 rounded border-white/40 text-teal-deep">
                                <label for="help-consent" class="text-xs leading-relaxed text-[#e2f3f7]">
                                    I agree that {{ $site->get('organisation_name') }} may use these details to contact me about my enquiry.
                                </label>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">Send Enquiry</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endif

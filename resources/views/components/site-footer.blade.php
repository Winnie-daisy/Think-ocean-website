@php
    $footerLinks = $navigation->for('footer');
    $legalLinks = $navigation->for('legal');
    $social = $site->socialLinks();
    $platforms = \App\Filament\Pages\ManageSettings::SOCIAL_PLATFORMS;
@endphp

<footer class="bg-navy-ink pb-7 pt-14 text-center text-[#cfe6ee]">
    <div class="container-site">
        <x-brand light stacked class="justify-center" />
        <p class="mt-3 text-sm">{{ $site->get('tagline') }}</p>

        @if ($footerLinks->isNotEmpty())
            <nav aria-label="Footer" class="mt-7">
                <ul class="mx-auto flex max-w-xl flex-wrap justify-center gap-x-6 gap-y-2 text-[13.5px]">
                    @foreach ($footerLinks as $link)
                        <li>
                            <a href="{{ $link['href'] }}" class="transition hover:text-white"
                               @if ($link['new_tab']) target="_blank" rel="noopener noreferrer" @endif>{{ $link['label'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif

        @if ($social)
            <ul class="mt-6 flex justify-center gap-3" aria-label="Social media">
                @foreach ($social as $link)
                    <li>
                        <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer me"
                           class="grid size-[38px] place-items-center rounded-full bg-white/10 text-white transition hover:bg-teal-deep">
                            <x-social-icon :platform="$link['platform']" />
                            <span class="sr-only">{{ $platforms[$link['platform']] ?? \Illuminate\Support\Str::headline($link['platform']) }} (opens in a new tab)</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif

        <div id="stay-connected" class="mx-auto mt-9 max-w-md scroll-mt-24">
            <h2 class="font-display text-base text-white">Stay connected</h2>
            <p class="mt-1 text-[13px]">Get updates and news.</p>
            <form method="POST" action="{{ route('subscribe') }}" class="mt-4 flex gap-2">
                @csrf
                <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                    <label for="subscribe-website">Leave this field empty</label>
                    <input type="text" id="subscribe-website" name="website" tabindex="-1" autocomplete="off">
                </div>
                <label for="subscribe-email" class="sr-only">Your email address</label>
                <input id="subscribe-email" type="email" name="email" required maxlength="190" autocomplete="email" placeholder="Your email address"
                       value="{{ $errors->subscribe->any() ? old('email') : '' }}"
                       @if ($errors->subscribe->has('email')) aria-invalid="true" aria-describedby="subscribe-feedback" @endif
                       class="min-w-0 flex-1 rounded-full border-0 bg-white px-4 py-2.5 text-sm text-charcoal placeholder:text-slate-muted focus:ring-[3px] focus:ring-teal/40 focus:outline-none">
                <button type="submit" class="btn btn-primary btn-sm min-h-10">Subscribe</button>
            </form>
            @if (session('subscribed'))
                <p id="subscribe-feedback" class="mt-2 text-[13px] text-[#9ff0ec]" role="status">{{ session('subscribed') }}</p>
            @elseif ($errors->subscribe->has('email'))
                <p id="subscribe-feedback" class="mt-2 text-[13px] text-[#ffb4b4]" role="alert">{{ $errors->subscribe->first('email') }}</p>
            @endif
        </div>

        <div class="mt-10 flex flex-wrap justify-center gap-x-5 gap-y-2 border-t border-white/10 pt-6 text-xs text-[#9fc3cf]">
            <span>&copy; {{ now()->year }} {{ $site->get('organisation_name') }}. All rights reserved.</span>
            @if ($site->get('company_registration'))
                <span>{{ $site->get('company_registration') }}</span>
            @endif
            @foreach ($legalLinks as $link)
                <a href="{{ $link['href'] }}" class="transition hover:text-white">{{ $link['label'] }}</a>
            @endforeach
            <a href="{{ url('/admin') }}" class="transition hover:text-white">Admin login</a>
        </div>
    </div>
</footer>

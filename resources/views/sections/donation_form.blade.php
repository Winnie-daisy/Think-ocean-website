@php
    $site = app(\App\Support\SiteSettings::class);
    $enabled = app(\App\Services\StripeCheckout::class)->isConfigured();
    $amounts = collect(explode(',', (string) ($data['amounts'] ?? '10,25,50,100')))
        ->map(fn ($amount) => (int) trim($amount))->filter(fn ($amount) => $amount > 0)->unique()->take(6)->values();
    $amounts = $amounts->isEmpty() ? collect([10, 25, 50, 100]) : $amounts;
    $allowMonthly = (bool) ($data['allow_monthly'] ?? true);
    $selected = (string) old('amount', $amounts->get(1, $amounts->first()));
    $hasCustom = filled(old('custom_amount'));
    $email = $site->get('contact_email');
    $enable = array_values(array_filter((array) ($data['items'] ?? []), fn ($item) => is_array($item) && filled($item['title'] ?? null)));
    $icons = array_keys(\App\Filament\Support\PageBlocks::ICONS);
@endphp

<section id="donate" class="section scroll-mt-24">
    <div class="container-site grid items-start gap-10 lg:grid-cols-[1.1fr_0.9fr]">
        <div data-reveal>
            <x-section-heading :heading="$data['heading'] ?? 'Make a donation'" :intro="$data['intro'] ?? null" />

            @if (session('donation_status'))
                <p class="mt-5 rounded-[10px] bg-mist px-4 py-3 text-sm font-medium text-navy" role="status">{{ session('donation_status') }}</p>
            @endif

            @if ($enabled)
                <form method="POST" action="{{ route('donate.store') }}" class="mt-6 rounded-[18px] border border-line bg-white p-6 shadow-card sm:p-8" novalidate>
                    @csrf
                    <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                        <label for="donate-website">Leave this field empty</label>
                        <input type="text" id="donate-website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    @if ($errors->any())
                        <div class="mb-5 rounded-[10px] bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                            <ul class="list-disc pl-5">
                                @foreach ($errors->all() as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($allowMonthly)
                        <fieldset>
                            <legend class="text-sm font-semibold text-navy">How often?</legend>
                            <div class="mt-2 inline-flex rounded-full bg-mist p-1">
                                @foreach (\App\Models\Donation::FREQUENCIES as $value => $label)
                                    <label class="cursor-pointer">
                                        <input type="radio" name="frequency" value="{{ $value }}" class="peer sr-only" @checked(old('frequency', 'one_off') === $value)>
                                        <span class="block rounded-full px-5 py-2 text-sm font-semibold text-navy transition peer-checked:bg-teal-deep peer-checked:text-white peer-focus-visible:ring-[3px] peer-focus-visible:ring-[#ffc857]">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @else
                        <input type="hidden" name="frequency" value="one_off">
                    @endif

                    <fieldset class="mt-6">
                        <legend class="text-sm font-semibold text-navy">Choose an amount</legend>
                        <div class="mt-2 grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                            @foreach ($amounts as $amount)
                                <label class="cursor-pointer">
                                    <input type="radio" name="amount" value="{{ $amount }}" class="peer sr-only" @checked(! $hasCustom && $selected === (string) $amount)>
                                    <span class="block rounded-[10px] border-2 border-line py-3 text-center font-display text-lg font-semibold text-navy transition hover:border-teal peer-checked:border-teal-deep peer-checked:bg-mist peer-focus-visible:ring-[3px] peer-focus-visible:ring-[#ffc857]">£{{ $amount }}</span>
                                </label>
                            @endforeach
                        </div>
                        <label for="donate-custom" class="mt-3 block text-[13px] text-slate-muted">Or enter another amount</label>
                        <div class="mt-1 flex items-center rounded-[10px] border border-[#cfdde3] bg-white focus-within:border-teal focus-within:ring-[3px] focus-within:ring-teal/15">
                            <span class="pl-3.5 font-semibold text-navy" aria-hidden="true">£</span>
                            <input id="donate-custom" name="custom_amount" inputmode="decimal" autocomplete="off" placeholder="Other amount" value="{{ old('custom_amount') }}"
                                   class="min-w-0 flex-1 border-0 bg-transparent px-2 py-3 text-[15px] outline-none">
                        </div>
                    </fieldset>

                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="donate-name" class="mb-1 block text-sm font-semibold text-navy">Name <span class="font-normal text-slate-muted">(optional)</span></label>
                            <input id="donate-name" name="name" maxlength="120" autocomplete="name" value="{{ old('name') }}" class="form-input">
                        </div>
                        <div>
                            <label for="donate-email" class="mb-1 block text-sm font-semibold text-navy">Email <span class="font-normal text-slate-muted">(for your receipt)</span></label>
                            <input id="donate-email" type="email" name="email" required maxlength="190" autocomplete="email" value="{{ old('email') }}" class="form-input">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary mt-7 w-full sm:w-auto">
                        <x-ocean-icon name="lock-closed" class="size-4" /> Continue to secure payment
                    </button>
                    <p class="mt-3 text-xs text-slate-muted">Payments are processed securely by Stripe. We never see or store your card details. Monthly gifts can be cancelled at any time by contacting us.</p>
                </form>
            @else
                <div class="mt-6 rounded-[18px] border border-dashed border-[#a9cbd6] bg-white p-6 sm:p-8">
                    <p class="font-display font-semibold text-navy">Online donations are coming soon.</p>
                    <p class="mt-2 text-slate-muted">
                        In the meantime, please get in touch to support our work.
                        @if ($email)
                            Email <a href="mailto:{{ $email }}?subject=Think%20Ocean%20Support%20Enquiry" class="link-arrow">{{ $email }}</a>.
                        @endif
                    </p>
                </div>
            @endif
        </div>

        @if ($enable)
            <aside class="tile p-7 sm:p-8" data-reveal>
                <h2 class="text-xl font-bold">{{ $data['items_heading'] ?? 'Your support can enable' }}</h2>
                <ul class="mt-5 grid gap-3.5" role="list">
                    @foreach ($enable as $item)
                        <li class="flex items-center gap-3 font-medium text-navy">
                            @if (in_array($item['icon'] ?? null, $icons, true))
                                <x-ocean-icon :name="$item['icon']" class="size-[22px] shrink-0 text-teal" />
                            @endif
                            {{ $item['title'] }}
                        </li>
                    @endforeach
                </ul>
            </aside>
        @endif
    </div>
</section>

<section class="section">
    <div class="container-site" data-reveal>
        <div class="max-w-3xl">
            <x-section-heading :eyebrow="$data['eyebrow'] ?? null" :heading="$data['heading'] ?? null" />
            <x-prose :html="$data['body'] ?? null" :class="filled($data['heading'] ?? null) || filled($data['eyebrow'] ?? null) ? 'mt-5' : 'text-slate-muted'" />
        </div>
    </div>
</section>

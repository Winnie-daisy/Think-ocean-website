@php
    /** @var \App\Models\ContentRevision $revision */
    $changes = $revision->changes();
    $format = function ($value): string {
        if ($value === null || $value === '' || $value === []) {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_array($value)) {
            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        // Rich text is shown as source so nothing in it is executed here.
        return (string) $value;
    };
@endphp

<div style="display:flex;flex-direction:column;gap:1rem">
    @if ($revision->comment)
        <div style="padding:.75rem 1rem;border-radius:.5rem;background:rgba(0,166,166,.08)">
            <strong>Comment:</strong> {{ $revision->comment }}
        </div>
    @endif

    @forelse ($changes as $field => $change)
        <div>
            <div style="font-weight:600;margin-bottom:.25rem">{{ \Illuminate\Support\Str::headline($field) }}</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
                <div>
                    <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;opacity:.7">Before</div>
                    <pre style="white-space:pre-wrap;word-break:break-word;font-size:.8rem;padding:.5rem;border-radius:.375rem;background:rgba(220,38,38,.06);max-height:20rem;overflow:auto">{{ $format($change['old']) }}</pre>
                </div>
                <div>
                    <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;opacity:.7">After</div>
                    <pre style="white-space:pre-wrap;word-break:break-word;font-size:.8rem;padding:.5rem;border-radius:.375rem;background:rgba(22,163,74,.06);max-height:20rem;overflow:auto">{{ $format($change['new']) }}</pre>
                </div>
            </div>
        </div>
    @empty
        <p>No content fields changed in this step.</p>
    @endforelse
</div>

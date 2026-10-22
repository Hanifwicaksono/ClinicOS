@props([
    'headingId',
    'title',
    'description' => null,
])

<section {{ $attributes->class(['clinic-card p-6 sm:p-8'])->merge(['aria-labelledby' => $headingId]) }}>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 id="{{ $headingId }}" class="text-lg font-extrabold text-clinic-950">{{ $title }}</h2>
            @if ($description)
                <p class="mt-1 text-sm leading-6 text-slate-500">{{ $description }}</p>
            @endif
        </div>

        @isset($actions)
            {{ $actions }}
        @endisset
    </div>

    <div class="mt-6">
        {{ $slot }}
    </div>
</section>

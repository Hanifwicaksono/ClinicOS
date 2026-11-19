@props(['eyebrow'=>'Pelayanan klinik', 'title', 'description'])
<section {{ $attributes->class(['mb-6 flex flex-col gap-5 rounded-3xl border border-clinic-200 bg-gradient-to-br from-white to-clinic-50/60 p-5 sm:p-7 sm:flex-row sm:items-center sm:justify-between']) }}>
    <div class="max-w-2xl"><p class="text-[10px] font-extrabold uppercase tracking-[0.18em] text-clinic-600">{{ $eyebrow }}</p><h2 class="mt-2 text-xl font-extrabold text-clinic-950 sm:text-2xl">{{ $title }}</h2><p class="mt-2 text-sm leading-6 text-slate-600">{{ $description }}</p></div>
    @if(!$slot->isEmpty())<div class="flex shrink-0 flex-wrap gap-2">{{ $slot }}</div>@endif
</section>

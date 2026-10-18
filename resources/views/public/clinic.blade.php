<x-public-layout :title="$clinic->name.' — ClinicOS'">
    <section class="relative overflow-hidden bg-[linear-gradient(135deg,_#253331_0%,_#287f78_56%,_#75afa8_100%)] text-white">
        <div class="absolute -left-24 -top-28 h-80 w-80 rounded-full bg-clinic-300/30 blur-3xl"></div>
        <div class="absolute -right-20 bottom-0 h-72 w-72 rounded-full bg-[#f8dec5]/25 blur-3xl"></div>
        <div class="relative mx-auto grid max-w-6xl gap-12 px-5 py-16 sm:px-8 lg:grid-cols-[1fr_.7fr] lg:items-center lg:py-24">
            <div class="landing-reveal">
                <div class="flex items-center gap-4"><div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-3xl bg-white p-2 shadow-lg">@if ($clinic->logo_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($clinic->logo_path) }}" alt="Logo {{ $clinic->name }}" class="h-full w-full object-contain">@else<span class="font-display text-3xl font-extrabold text-clinic-700">{{ str($clinic->name)->substr(0, 1)->upper() }}</span>@endif</div><span class="inline-flex rounded-full bg-white/10 px-3 py-1.5 text-xs font-bold uppercase tracking-[0.16em] text-clinic-100">Informasi klinik</span></div>
                <h1 class="mt-6 font-display text-4xl font-extrabold leading-[1.08] tracking-tight sm:text-5xl">{{ $clinic->name }}</h1>
                <p class="mt-5 max-w-2xl text-base leading-8 text-white/80 sm:text-lg">{{ $clinic->description ?: 'Pelayanan kesehatan dengan pendaftaran yang mudah dan jadwal yang transparan.' }}</p>

                @if ($clinic->address || $clinic->phone)
                    <div class="mt-7 flex flex-wrap gap-3 text-sm font-semibold text-white/85">
                        @if ($clinic->address)<span class="rounded-full bg-white/10 px-4 py-2">{{ $clinic->address }}</span>@endif
                        @if ($clinic->phone)<span class="rounded-full bg-white/10 px-4 py-2">{{ $clinic->phone }}</span>@endif
                    </div>
                @endif

                <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('public.queue.index', $clinic) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-white px-6 py-3.5 text-sm font-bold text-clinic-950 shadow-lg transition hover:bg-clinic-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-clinic-600">Lihat antrean</a>
                    <a href="{{ route('public.booking.create', $clinic) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#f8dec5] px-6 py-3.5 text-sm font-bold text-clinic-950 shadow-lg transition hover:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-[#f8dec5] focus-visible:ring-offset-2 focus-visible:ring-offset-clinic-600">Buat booking</a>
                </div>
            </div>

            <aside class="landing-reveal landing-reveal-delay rounded-[2rem] bg-white/10 p-5 backdrop-blur-sm sm:p-7">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-100">Ringkasan layanan</p>
                <div class="mt-6 grid grid-cols-2 gap-3">
                    <div class="rounded-2xl bg-white p-5 text-clinic-950"><p class="font-display text-3xl font-extrabold">{{ $clinic->services->count() }}</p><p class="mt-1 text-sm font-semibold text-slate-500">layanan aktif</p></div>
                    <div class="rounded-2xl bg-white/10 p-5"><p class="font-display text-3xl font-extrabold">{{ $clinic->doctors->count() }}</p><p class="mt-1 text-sm font-semibold text-white/75">dokter praktik</p></div>
                </div>
                <div class="mt-4 rounded-2xl bg-[#f8dec5]/15 p-5"><p class="text-sm font-bold">Rencanakan kunjungan Anda</p><p class="mt-2 text-sm leading-6 text-white/75">Pilih layanan dan sesi dokter yang sesuai sebelum membuat booking.</p></div>
            </aside>
        </div>
    </section>

    <section class="bg-[#f7f8f5] py-20 lg:py-28">
        <div class="mx-auto max-w-6xl px-5 sm:px-8">
            <div class="max-w-2xl"><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Layanan aktif</p><h2 class="mt-3 font-display text-3xl font-extrabold text-clinic-950 sm:text-4xl">Pilih kebutuhan perawatan Anda.</h2><p class="mt-4 leading-7 text-slate-600">Lihat layanan yang tersedia, perkiraan durasi, dan biaya sebelum melanjutkan ke booking.</p></div>
            <div class="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-3">@forelse($clinic->services as $service)<article class="rounded-3xl bg-white p-6 shadow-card transition duration-300 hover:-translate-y-1 hover:shadow-elevated"><h3 class="text-lg font-extrabold text-slate-900">{{ $service->name }}</h3><p class="mt-3 min-h-12 text-sm leading-6 text-slate-500">{{ $service->description ?: 'Informasi layanan tersedia saat booking.' }}</p><div class="mt-6 flex items-end justify-between"><p class="font-display text-lg font-extrabold text-clinic-700">Rp {{ number_format((float) $service->price, 0, ',', '.') }}</p><span class="rounded-full bg-clinic-50 px-3 py-1.5 text-xs font-bold text-clinic-700">{{ $service->duration_minutes }} menit</span></div></article>@empty<p class="text-slate-500">Belum ada layanan aktif.</p>@endforelse</div>
        </div>
    </section>

    <section class="relative overflow-hidden bg-[linear-gradient(135deg,_#e1f0ed_0%,_#f8fcfa_52%,_#fff0e3_100%)] py-20 lg:py-28">
        <div class="absolute -right-20 top-0 h-72 w-72 rounded-full bg-clinic-200/60 blur-3xl"></div>
        <div class="relative mx-auto max-w-6xl px-5 sm:px-8">
            <div class="max-w-2xl"><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Dokter & sesi</p><h2 class="mt-3 font-display text-3xl font-extrabold text-clinic-950 sm:text-4xl">Jadwal Praktik Dokter Aktif</h2><p class="mt-4 leading-7 text-slate-600">Temukan dokter dan waktu konsultasi yang tersedia untuk kunjungan Anda.</p></div>
            <div class="mt-10 grid gap-5 lg:grid-cols-2">@forelse($clinic->doctors as $doctor)<article class="rounded-3xl bg-white/90 p-6 shadow-card"><div class="flex items-start gap-4"><div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-clinic-500 font-display text-lg font-extrabold text-white">{{ str($doctor->user->name)->substr(0, 1)->upper() }}</div><div><h3 class="font-extrabold text-slate-900">{{ $doctor->user->name }}</h3><p class="mt-1 text-sm text-clinic-600">{{ $doctor->specialization ?: 'Dokter' }}</p></div></div><div class="mt-6 flex flex-wrap gap-2">@forelse($doctor->schedules as $schedule)<span class="rounded-xl bg-clinic-50 px-3 py-2 text-xs font-semibold text-clinic-700">{{ $schedule->day_name }}, {{ substr($schedule->start_time, 0, 5) }}–{{ substr($schedule->end_time, 0, 5) }}</span>@empty<span class="text-sm text-slate-500">Jadwal belum tersedia.</span>@endforelse</div></article>@empty<p class="text-slate-500">Belum ada jadwal dokter aktif.</p>@endforelse</div>
        </div>
    </section>

    <section class="bg-clinic-950 py-16 text-white sm:py-20"><div class="mx-auto flex max-w-6xl flex-col gap-6 px-5 sm:px-8 lg:flex-row lg:items-center lg:justify-between"><div><p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-300">Siap berkunjung?</p><h2 class="mt-3 font-display text-3xl font-extrabold">Atur kunjungan Anda dengan lebih mudah.</h2></div><x-clinic-button href="{{ route('public.booking.create', $clinic) }}" variant="primary" class="shrink-0 px-6 py-3.5">Buat booking</x-clinic-button></div></section>
</x-public-layout>

<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Portal akun</p>
            <h1 class="mt-1 text-xl font-extrabold text-clinic-950">{{ $dashboardTitle ?? 'Akses akun' }}</h1>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl">
        <section class="overflow-hidden rounded-3xl bg-clinic-950 p-7 text-white shadow-elevated sm:p-10">
            <p class="text-sm font-semibold text-clinic-200">Selamat datang, {{ auth()->user()->name }}</p>
            <h2 class="mt-3 text-3xl font-extrabold">Informasi penting Anda, dalam satu tempat.</h2>
            <p class="mt-4 max-w-2xl text-sm leading-7 text-slate-300">{{ $dashboardDescription ?? 'Akun Anda belum memiliki role. Hubungi administrator klinik untuk mendapatkan akses.' }}</p>
            <div class="mt-7 flex flex-col gap-3 sm:flex-row">
                @if (auth()->user()->hasRole('Patient'))
                    <x-clinic-button href="{{ route('patient.appointments.index') }}" wire:navigate>Lihat booking saya</x-clinic-button>
                @endif
                <x-clinic-button href="{{ route('profile') }}" variant="secondary" wire:navigate class="border-white/20 bg-white/10 text-white hover:bg-white/20 hover:text-white">Kelola profil akun</x-clinic-button>
            </div>
        </section>

        @isset($medicalRecords)
            <section class="mt-8" aria-labelledby="prescriptions-heading">
                <div class="mb-5">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Riwayat pengobatan</p>
                    <h2 id="prescriptions-heading" class="mt-1 text-2xl font-extrabold text-clinic-950">Resep saya</h2>
                    <p class="mt-2 text-sm text-slate-600">Resep tampil setelah dokter menyelesaikan visit.</p>
                </div>

                <div class="space-y-5">
                    @forelse ($medicalRecords as $medicalRecord)
                        <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div class="flex flex-col gap-2 border-b border-slate-100 pb-4 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h3 class="font-extrabold text-clinic-950">{{ $medicalRecord->visit->clinic->name }}</h3>
                                    <p class="mt-1 text-sm text-slate-600">Dokter {{ $medicalRecord->doctor->user->name }}</p>
                                </div>
                                <time class="text-sm font-semibold text-clinic-700" datetime="{{ $medicalRecord->finalized_at->toDateString() }}">
                                    {{ $medicalRecord->finalized_at->format('d M Y') }}
                                </time>
                            </div>

                            @if (filled($medicalRecord->prescription->notes))
                                <p class="mt-4 rounded-2xl bg-clinic-50 px-4 py-3 text-sm leading-6 text-clinic-950">
                                    {{ $medicalRecord->prescription->notes }}
                                </p>
                            @endif

                            @if ($medicalRecord->prescription->items->isEmpty())
                                <p class="mt-4 text-sm text-slate-500">Tidak ada item obat pada resep ini.</p>
                            @else
                                <ul class="mt-4 divide-y divide-slate-100">
                                    @foreach ($medicalRecord->prescription->items as $item)
                                        <li class="py-4 first:pt-0 last:pb-0">
                                            <p class="font-bold text-slate-900">{{ $item->medicine_name }}</p>
                                            <p class="mt-1 text-sm text-slate-600">
                                                {{ $item->dosage }} · {{ $item->frequency }} · {{ $item->quantity }}{{ filled($item->unit) ? ' '.$item->unit : '' }}
                                            </p>
                                            @if (filled($item->instructions))
                                                <p class="mt-1 text-sm text-slate-500">{{ $item->instructions }}</p>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </article>
                    @empty
                        <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center">
                            <p class="font-bold text-clinic-950">Belum ada resep</p>
                            <p class="mt-2 text-sm text-slate-500">Resep dari visit yang sudah selesai akan muncul di sini.</p>
                        </div>
                    @endforelse
                </div>

                @if ($medicalRecords->hasPages())
                    <div class="mt-6">{{ $medicalRecords->links() }}</div>
                @endif
            </section>
        @endisset
    </div>
</x-app-layout>

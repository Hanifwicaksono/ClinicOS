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
                    <x-clinic-button href="{{ route('patient.appointments.create') }}" wire:navigate>Booking baru</x-clinic-button>
                    <x-clinic-button href="{{ route('patient.appointments.index') }}" variant="secondary" wire:navigate>Lihat booking saya</x-clinic-button>
                @endif
                <x-clinic-button href="{{ route('profile') }}" variant="secondary" wire:navigate class="border-white/20 bg-white/10 text-white hover:bg-white/20 hover:text-white">Kelola profil akun</x-clinic-button>
            </div>
        </section>

        @isset($issuedPrescriptions)
            <section class="mt-8" aria-labelledby="prescriptions-heading"><h2 id="prescriptions-heading" class="text-2xl font-extrabold">Resep saya</h2><p class="mt-2 text-sm text-slate-600">Hanya dokumen yang diterbitkan dan dirilis untuk Anda. Isi resep mengikuti versi yang dirilis dokter.</p>
            <div class="mt-5 space-y-5">@forelse($issuedPrescriptions as $document)
            @php($snapshot=$document->snapshot)
            <article class="clinic-card p-6"><h3 class="font-bold">{{ $snapshot['clinic']['name'] }}</h3><p class="mt-1 text-sm">Dokter {{ $snapshot['doctor']['name'] }} | {{ $document->document_number }} | Versi {{ $document->version_number }}</p>
            @if($document->status!=='ISSUED')<p class="mt-4 rounded-xl bg-red-50 p-3 font-bold text-red-800">Tidak berlaku - {{ $document->status }}. Hubungi klinik untuk instruksi yang berlaku.</p>
            @else
            @if(filled($snapshot['notes']))<p class="mt-4 whitespace-pre-wrap rounded-xl bg-clinic-50 p-3 text-sm">{{ $snapshot['notes'] }}</p>@endif
            <ul class="mt-4 divide-y">@foreach($snapshot['items'] as $item)<li class="py-3"><p class="font-bold">{{ $item['medicine_name'] }}</p><p class="mt-1 text-sm">{{ $item['dosage'] }} | {{ $item['frequency'] }} | {{ $item['quantity'] }}{{ filled($item['unit']??null)?' '.$item['unit']:'' }}</p>@if(filled($item['instructions']??null))<p class="mt-1 text-sm">{{ $item['instructions'] }}</p>@endif</li>@endforeach</ul>
            @endif
            <a class="mt-4 inline-block font-bold underline" href="{{ route('clinical-documents.show',$document) }}">Lihat dan cetak resep</a></article>
            @empty<div class="clinic-card p-6"><p class="font-bold">Belum ada resep</p><p class="mt-2 text-sm text-slate-500">Resep tampil setelah dokter menerbitkan dan merilis dokumen untuk Anda.</p></div>@endforelse</div>{{ $issuedPrescriptions->links() }}
            </section>
        @endisset
    </div>
</x-app-layout>

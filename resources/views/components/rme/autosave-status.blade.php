@props([
    'state' => 'draft',
    'message' => null,
])

@php
    $states = [
        'ready' => [
            'label' => 'Autosave aktif',
            'message' => 'Manual dan foto: pilih Simpan draft.',
            'class' => 'border-slate-200 bg-slate-50 text-slate-700',
            'dot' => 'bg-slate-400',
        ],
        'draft' => [
            'label' => 'Perubahan belum tersimpan',
            'message' => 'Menunggu autosave atau pilih Simpan draft.',
            'class' => 'border-slate-200 bg-slate-50 text-slate-700',
            'dot' => 'bg-slate-400',
        ],
        'saving' => [
            'label' => 'Menyimpan…',
            'message' => 'Data sedang dikirim.',
            'class' => 'border-amber-200 bg-amber-50 text-amber-900',
            'dot' => 'animate-pulse bg-amber-500',
        ],
        'saved' => [
            'label' => 'Tersimpan',
            'message' => 'Perubahan terakhir sudah tersimpan.',
            'class' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
            'dot' => 'bg-emerald-500',
        ],
        'photos' => [
            'label' => 'Foto belum disimpan',
            'message' => 'Teks tersimpan. Simpan draft untuk menerapkan perubahan foto.',
            'class' => 'border-amber-200 bg-amber-50 text-amber-900',
            'dot' => 'bg-amber-500',
        ],
        'error' => [
            'label' => 'Gagal disimpan',
            'message' => 'Periksa field yang ditandai.',
            'class' => 'border-red-200 bg-red-50 text-red-800',
            'dot' => 'bg-red-500',
        ],
        'conflict' => [
            'label' => 'Perlu ditinjau',
            'message' => 'Salin perubahan Anda lalu muat ulang halaman untuk melanjutkan.',
            'class' => 'border-amber-300 bg-amber-50 text-amber-950',
            'dot' => 'bg-amber-500',
        ],
        'expired' => [
            'label' => 'Sesi berakhir',
            'message' => 'Salin perubahan Anda lalu masuk kembali di tab baru. Setelah itu muat ulang editor.',
            'class' => 'border-amber-300 bg-amber-50 text-amber-950',
            'dot' => 'bg-amber-500',
        ],
        'finalized' => [
            'label' => 'Finalized',
            'message' => 'Perubahan berikutnya menjadi Modification.',
            'class' => 'border-clinic-200 bg-clinic-50 text-clinic-800',
            'dot' => 'bg-clinic-500',
        ],
        'amending' => [
            'label' => 'Koreksi belum disimpan',
            'message' => 'Isi alasan, lalu simpan Modification untuk mencatat revisi.',
            'class' => 'border-amber-200 bg-amber-50 text-amber-900',
            'dot' => 'bg-amber-500',
        ],
    ];
    $state = array_key_exists($state, $states) ? $state : 'draft';
@endphp

<div
    {{ $attributes->class(['min-w-48'])->merge([
        'data-rme-autosave-status' => '',
        'data-initial-state' => $state,
        'role' => 'status',
        'aria-live' => 'polite',
        'aria-atomic' => 'true',
    ]) }}
    x-data="{
        state: @js($state),
        message: @js($message),
        setState(detail) {
            if (! detail || ! @js(array_keys($states)).includes(detail.state)) {
                return;
            }

            this.state = detail.state;
            this.message = detail.message ?? null;
        },
    }"
    x-on:rme-autosave-state.window="setState($event.detail)"
>
    @foreach ($states as $stateName => $configuration)
        <div
            x-show="state === '{{ $stateName }}'"
            x-cloak
            class="flex items-start gap-2 rounded-xl border px-3 py-2 text-xs {{ $configuration['class'] }}"
        >
            <span class="mt-1 size-2 shrink-0 rounded-full {{ $configuration['dot'] }}" aria-hidden="true"></span>
            <span>
                <span class="block font-extrabold">{{ $configuration['label'] }}</span>
                <span
                    class="mt-0.5 block leading-5"
                    @if (in_array($stateName, ['error', 'conflict', 'expired'])) x-text="message || @js($configuration['message'])" @endif
                >{{ $configuration['message'] }}</span>
                @if ($stateName === 'error')
                    <button type="button" class="mt-1 min-h-11 font-bold underline focus-visible:ring-2 focus-visible:ring-clinic-500" @click="$dispatch('rme-retry')">Coba simpan lagi</button>
                @endif
                @if ($stateName === 'expired')
                    <a href="{{ route('admin.login') }}" target="_blank" rel="noopener" class="mt-1 inline-flex min-h-11 items-center font-bold underline focus-visible:ring-2 focus-visible:ring-clinic-500">Masuk kembali</a>
                @endif
            </span>
        </div>
    @endforeach
</div>

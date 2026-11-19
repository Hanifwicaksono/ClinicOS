@props([
    'status',
])

@php
    $statusValue = $status instanceof \App\QueueStatus ? $status->value : (string) $status;
    $statusLabel = $status instanceof \App\QueueStatus ? $status->label() : match ($statusValue) {
        'BOOKED' => 'Terdaftar',
        'WAITING' => 'Menunggu',
        'CALLED' => 'Dipanggil',
        'IN_PROGRESS' => 'Diperiksa',
        'PAUSED' => 'Dijeda',
        'INTERRUPTED' => 'Pelayanan terinterupsi',
        'COMPLETED' => 'Selesai',
        'SKIPPED' => 'Dilewati',
        'CANCELLED' => 'Dibatalkan',
        'NO_SHOW' => 'Tidak hadir',
        'APPLIED' => 'Diterapkan',
        'CORRECTED' => 'Sudah dikoreksi', 'PENDING_REVIEW' => 'Menunggu persetujuan',
        'NONE' => 'Tidak ada', 'REFUND_DUE' => 'Perlu penyelesaian dana berlebih',
        'OPEN' => 'Perlu ditindaklanjuti', 'DRAFT' => 'Draf', 'ISSUED' => 'Berlaku',
        'SUPERSEDED' => 'Sudah diganti', 'RECALLED' => 'Ditarik dokter', 'REQUESTED' => 'Menunggu tinjauan',
        'ACCEPTED' => 'Diterima', 'DECLINED' => 'Ditolak', 'EXPIRED' => 'Berakhir',
        'READY' => 'Siap', 'APPROVED' => 'Disetujui', 'REJECTED' => 'Ditolak',
        'PENDING' => 'Sedang disiapkan', 'FAILED' => 'Belum berhasil', 'VERIFIED' => 'Terverifikasi',
        'PENDING_VERIFICATION' => 'Menunggu verifikasi dana', 'PAID' => 'Lunas',
        'SETTLED' => 'Kewajiban diselesaikan', 'UNPAID' => 'Belum dibayar', 'PARTIALLY_PAID' => 'Dibayar sebagian',
        'NO_PAYMENT_DUE' => 'Tanpa tagihan', 'RESOLVED' => 'Terselesaikan',
        'WAITING_DOCTOR' => 'Menunggu dokter', 'WAITING_PATIENT' => 'Menunggu pasien',
        default => \Illuminate\Support\Str::headline($statusValue),
    };
    $statusClasses = match ($statusValue) {
        'BOOKED' => 'bg-slate-100 text-slate-700',
        'WAITING' => 'bg-[#FFF4E8] text-[#8A5427]',
        'CALLED' => 'bg-[#EAF2F0] text-[#287F78]',
        'IN_PROGRESS' => 'bg-[#EEF3F2] text-[#3D5F5C]',
        'COMPLETED', 'ISSUED', 'VERIFIED', 'PAID', 'READY', 'APPROVED', 'ACCEPTED', 'RESOLVED', 'APPLIED' => 'bg-clinic-50 text-clinic-800',
        'PAUSED', 'OPEN', 'REQUESTED', 'PENDING_VERIFICATION', 'WAITING_DOCTOR', 'WAITING_PATIENT', 'PARTIALLY_PAID', 'UNPAID', 'REFUND_DUE' => 'bg-amber-50 text-amber-900',
        'RECALLED', 'FAILED', 'REJECTED' => 'bg-red-50 text-red-800',
        'SKIPPED' => 'bg-orange-100 text-orange-700',
        'CANCELLED' => 'bg-slate-100 text-slate-500',
        'NO_SHOW' => 'bg-red-100 text-red-700',
        default => 'bg-slate-100 text-slate-600',
    };
@endphp

<span data-status="{{ $statusValue }}" {{ $attributes->class(["inline-flex max-w-full shrink-0 [overflow-wrap:normal] rounded-full px-2.5 py-1 text-xs font-bold {$statusClasses}"]) }}>
    {{ $statusLabel }}
</span>


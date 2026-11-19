@php
$metricLabels = [
    'bookings_created_count'=>'Booking dibuat',
    'patients_on_created_bookings_count'=>'Pasien unik pada booking baru',
    'visit_episodes_started_count'=>'Episode pemeriksaan dimulai',
    'visit_episodes_completed_count'=>'Episode pemeriksaan selesai',
    'reschedule_events_count'=>'Perubahan jadwal',
    'pause_events_count'=>'Pemeriksaan dijeda',
    'resume_events_count'=>'Pemeriksaan dilanjutkan',
    'interruption_events_count'=>'Pelayanan terinterupsi',
    'prescriptions_issued_count'=>'Resep resmi diterbitkan',
    'scheduled_queue_count'=>'Antrean pada tanggal terpilih',
    'checked_in_events_count'=>'Peristiwa check-in',
    'returned_events_count'=>'Kembali menunggu setelah dilewati',
    'called_events_count'=>'Peristiwa panggilan',
    'completed_service_episode_count'=>'Episode pelayanan selesai',
    'protected_continuation_episodes_started_count'=>'Episode kelanjutan dengan hak asal',
    'completed_episode_snapshot_value_not_cash_minor'=>'Nilai snapshot episode selesai (bukan kas)',
    'bills_finalized_count'=>'Tagihan difinalisasi',
    'net_billed_change_minor'=>'Perubahan bersih tagihan',
    'net_allocated_payment_change_minor'=>'Perubahan pembayaran teralokasi',
    'net_writeoff_change_minor'=>'Perubahan write-off',
    'verified_receipts_minor'=>'Penerimaan dana terverifikasi',
    'verified_deposits_minor'=>'Uang muka terverifikasi (bagian dari penerimaan)',
    'deposit_allocations_not_new_cash_minor'=>'Alokasi uang muka (bukan penerimaan baru)',
    'actual_refunds_out_minor'=>'Pengembalian dana yang sudah terlaksana',
    'receipt_corrections_minor'=>'Koreksi pencatatan penerimaan',
    'net_cash_minor'=>'Penerimaan bersih setelah pengembalian dan koreksi',
    'unallocated_funds_change_minor'=>'Perubahan dana belum dialokasikan',
    'payments_created_in_period_still_pending_count'=>'Transaksi periode ini masih menunggu verifikasi',
    'refunds_approved_in_period_still_pending_count'=>'Pengembalian disetujui pada periode ini, belum terlaksana',
];
@endphp
<x-app-layout><x-slot name="header"><div class="flex flex-wrap items-center gap-3"><h1 class="text-xl font-extrabold">Laporan #{{ $report->id }}</h1><x-status-badge :status="$report->status" /></div></x-slot><div class="space-y-6"><section class="clinic-card p-5"><p class="font-bold">{{ ['activity'=>'Aktivitas','queue'=>'Antrean','service'=>'Pelayanan','finance'=>'Keuangan'][$report->kind] }} · {{ $report->timezone }}</p><p class="mt-2">{{ $report->period_starts_at->setTimezone($report->timezone)->format('d M Y H:i') }} (termasuk) - {{ $report->period_ends_at->setTimezone($report->timezone)->format('d M Y H:i') }} (eksklusif)</p><p class="mt-2 text-sm">Definisi {{ $report->definition_version }} | Masa unduh sampai {{ $report->expires_at->setTimezone($report->timezone)->format('d M Y H:i') }}</p>
@if($report->status==='READY' && $report->expires_at->isFuture())<a class="clinic-button-primary mt-5 inline-flex" href="{{ route('clinic-reports.download',$report) }}">Unduh CSV agregat</a><p class="mt-3 text-sm">Laporan disiapkan pada {{ $report->generated_at->setTimezone($report->timezone)->format('d M Y H:i:s') }}. Angka di bawah sama dengan isi berkas, meski data operasional kemudian berubah.</p>@elseif($report->status==='FAILED')<p class="mt-4 rounded-xl bg-amber-50 p-3">Laporan belum berhasil dibuat atau akses/masa berlakunya berubah. Periksa izin dan buat permintaan baru bila diperlukan.</p>@elseif($report->expires_at->isPast())<p class="mt-4">Masa unduh berakhir. Buat permintaan baru jika masih diperlukan.</p>@else<p class="mt-4" role="status">Laporan dalam antrean. Anda mendapat notifikasi in-app setelah berkas selesai.</p><a class="mt-4 inline-block underline" href="{{ route('clinic-reports.show',$report) }}">Periksa status terbaru</a>@endif</section>
@if($report->status==='READY')<section class="clinic-card p-5"><h2 class="font-bold">Definisi dan satuan</h2><p class="mt-3 text-sm">Angka menghitung entitas atau peristiwa sesuai label. Nominal ditampilkan dalam rupiah; CSV memakai satuan 0,01 rupiah (100 minor = Rp1). Nilai layanan pada pemeriksaan yang selesai berbeda dari penerimaan kas. Perubahan tagihan, alokasi dan write-off berasal dari peristiwa pada periode; deposit teralokasi tidak menciptakan kas baru. Status antrean adalah keadaan saat snapshot dibuat, sedangkan kembali ke antrean dihitung sebagai peristiwa terpisah.</p><div class="mt-4 divide-y">@foreach($report->metrics as $metric=>$value)<div class="flex flex-wrap justify-between gap-3 py-3"><span class="min-w-0 break-all text-sm">{{ $metricLabels[$metric] ?? (str_starts_with($metric,'scheduled_queue_current_status_') ? 'Status saat laporan dibuat: '.(\App\QueueStatus::tryFrom(substr($metric,31,-6))?->label() ?? $metric) : \Illuminate\Support\Str::headline($metric)) }}</span><strong>@if(str_ends_with($metric,'_minor'))<x-billing.money :minor="$value" />@else{{ number_format($value) }}@endif</strong></div>@endforeach</div></section>@endif
<a class="inline-block underline" href="{{ route('clinic-reports.index') }}">Kembali ke laporan</a></div></x-app-layout>

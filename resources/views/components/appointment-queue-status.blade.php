@props(['appointment', 'eta'])
<section class="mt-6 space-y-4" data-realtime-queue-channel="clinic.{{ $appointment->clinic_id }}.queue-board" data-realtime-public data-realtime-refresh-root aria-label="Perkembangan antrean Anda">
    <div class="flex items-center gap-2 text-sm font-semibold text-slate-600" data-realtime-connection-state="reconnecting" role="status" aria-live="polite"><span data-realtime-connection-dot class="h-2 w-2 rounded-full bg-amber-400" aria-hidden="true"></span><span data-realtime-connection-label>Menghubungkan pembaruan antrean…</span></div>
    <x-queue-number-display :display-number="$appointment->queue->display_number" :poli-label="$appointment->doctor->specialization" :doctor-label="$appointment->doctor->user->name" :status="$appointment->queue->status->value" :eta-min-minutes="$eta['eta_min_minutes'] ?? null" :eta-max-minutes="$eta['eta_max_minutes'] ?? null" :confidence="$eta['confidence'] ?? null" />
    @if($eta === null)<p class="text-sm leading-6 text-slate-500">Estimasi tersedia setelah check-in pada hari kunjungan dan selama antrean masih aktif.</p>@endif
</section>

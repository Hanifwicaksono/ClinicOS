@if($schedules->count() === 1)
    @php($session = $schedules->first())
    <input type="hidden" name="doctor_schedule_id" value="{{ $session->id }}">
    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3"><p class="text-xs font-semibold text-slate-500">Sesi hari ini</p><p class="mt-1 text-sm font-bold text-clinic-950">{{ substr($session->start_time,0,5) }} – {{ substr($session->end_time,0,5) }}</p></div>
@elseif($schedules->isNotEmpty())
    <label class="clinic-label">Pilih sesi hari ini<select class="clinic-input mt-2" name="doctor_schedule_id" required>@foreach($schedules as $session)<option value="{{ $session->id }}" @selected($session->id === ($visit->resumeReservation?->doctor_schedule_id ?? $visit->queue->doctor_schedule_id))>{{ substr($session->start_time,0,5) }} – {{ substr($session->end_time,0,5) }}{{ $session->is_active ? '' : ' (sesi pemeriksaan ini)' }}</option>@endforeach</select></label>
@else
    <p class="rounded-xl bg-amber-50 p-4 text-sm leading-6 text-amber-900" role="status">Belum ada sesi dokter yang sesuai untuk hari ini. Hubungi admin klinik untuk memeriksa jadwal sebelum melanjutkan.</p>
@endif

@php
    $canResume = auth()->user()->can('changeWorkflow', [$visit, 'resume']);
    $canReady = auth()->user()->can('changeWorkflow', [$visit, 'ready']);
    $hasReady = $visit->resumeReservation?->state === 'READY';
    $isReady = $hasReady && $visit->resumeReservation->resume_date->isSameDay(\Carbon\CarbonImmutable::now($timezone));
    $crossDay = !$visit->queue->queue_date->isSameDay(\Carbon\CarbonImmutable::now($timezone));
@endphp
@if($canResume || $canReady)
<section class="clinic-card overflow-hidden" data-pause-actions>
    <div class="border-b border-slate-100 bg-clinic-50/60 p-5 sm:p-6"><div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-lg font-extrabold text-clinic-950">{{ $canResume ? 'Lanjutkan pemeriksaan' : 'Kesiapan pasien' }}</h2>@if($isReady)<span class="clinic-status-active">Pasien sudah ditandai siap</span>@else<x-status-badge status="PAUSED" />@endif</div><p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">{{ $canResume ? 'Pastikan pasien sudah kembali. Catatan sebelumnya tetap tersimpan pada pemeriksaan yang sama.' : 'Catat kesiapan pasien agar dokter dapat mengatur kelanjutan pemeriksaan.' }}</p></div>
    <div class="space-y-5 p-5 sm:p-6">
    @if($canResume)
        <form class="space-y-4" method="POST" action="{{ route('care-continuity.workflow',[$visit,'resume']) }}" data-care-form>
            @include('care-continuity.version')
            @include('care-continuity.session-field')
            <label class="flex items-start gap-3 rounded-xl border border-clinic-200 bg-clinic-50/50 p-4 text-sm font-semibold"><input class="mt-0.5 rounded border-slate-300 text-clinic-600 focus:ring-clinic-500" type="checkbox" name="patient_ready" value="1" required><span>Pasien siap melanjutkan pemeriksaan</span></label>
            @if($crossDay)<label class="clinic-label">Penilaian klinis setelah pergantian hari<textarea class="clinic-input mt-2" name="clinical_delay_assessment" required maxlength="5000" rows="3" placeholder="Catat kondisi terkini dan pertimbangan melanjutkan pemeriksaan.">{{ old('clinical_delay_assessment') }}</textarea></label>@endif
            <label class="clinic-label">Catatan klinis kelanjutan<textarea class="clinic-input mt-2" name="reason" required maxlength="2000" rows="2" placeholder="Catat alasan dan kondisi yang mendukung kelanjutan pemeriksaan.">{{ old('reason') }}</textarea></label>
            <div class="flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between"><p class="max-w-md text-xs leading-5 text-slate-500">Jika sesi masih melayani pasien lain, tunggu sampai panggilan atau pemeriksaan aktif selesai.</p><button class="clinic-button-primary shrink-0" type="submit" @disabled($schedules->isEmpty())>Lanjutkan pemeriksaan</button></div>
        </form>
    @endif
    @if($canReady && !$isReady)
        @if($canResume)<details class="clinic-disclosure"><summary>Pasien sudah kembali, tetapi belum akan diperiksa?</summary><div class="pt-4"><p class="mb-4 text-sm leading-6 text-slate-500">Gunakan penanda siap bila pasien masih menunggu dokter. Pemeriksaan tetap dalam jeda.</p>@endif
        <form class="space-y-4" method="POST" action="{{ route('care-continuity.workflow',[$visit,'ready']) }}" data-care-form>
            @include('care-continuity.version')
            @include('care-continuity.session-field')
            <label class="clinic-label">Catatan kesiapan pasien<textarea class="clinic-input mt-2" name="reason" required maxlength="2000" rows="2" placeholder="Contoh: pasien sudah kembali dan menunggu dokter. Jangan tulis isi medis di sini.">{{ old('reason') }}</textarea></label>
            <button class="{{ $canResume ? 'clinic-button-secondary' : 'clinic-button-primary' }}" type="submit" @disabled($schedules->isEmpty())>Tandai siap kembali</button>
        </form>
        @if($canResume)</div></details>@endif
    @endif
    @if($hasReady && auth()->user()->can('changeWorkflow',[$visit,'clear-ready']))
        <details class="clinic-disclosure"><summary>Pasien belum siap? Ubah penanda kesiapan</summary><form class="mt-4 space-y-4" method="POST" action="{{ route('care-continuity.workflow',[$visit,'clear-ready']) }}" data-care-form>
            @include('care-continuity.version')
            <label class="clinic-label">Alasan menarik kesiapan<textarea class="clinic-input mt-2" name="reason" required maxlength="2000" rows="2" placeholder="Contoh: pasien meninggalkan ruang tunggu.">{{ old('reason') }}</textarea></label>
            <button class="clinic-button-secondary" type="submit">Tarik kesiapan kembali</button>
        </form></details>
    @endif
    </div>
</section>
@endif

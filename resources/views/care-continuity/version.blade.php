@csrf
<input type="hidden" name="request_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
<input type="hidden" name="workflow_version" value="{{ $visit->workflow_version }}">
<input type="hidden" name="record_version" value="{{ auth()->user()->hasRole('Doctor') ? ($visit->medicalRecord?->lock_version ?? 0) : 0 }}">
<p role="alert" class="text-sm text-red-700" data-care-error></p>
<a href="{{ route('care-continuity.show',$visit) }}" target="_blank" rel="noopener" class="hidden text-sm font-bold underline" data-care-review>Tinjau status terbaru di tab baru; pertahankan input ini</a>

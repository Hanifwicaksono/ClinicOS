<?php

namespace App\Http\Controllers;

use App\Models\ClinicReportExport;
use App\Services\AuditLogger;
use App\Services\ClinicReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClinicReportController extends Controller
{
    public function index(Request $request, ClinicReportService $service): View
    {
        $actor = $request->user();
        abort_unless($actor->clinic_id !== null, 403);
        $kinds = collect(['activity', 'queue', 'service', 'finance'])->filter(fn ($kind) => $service->canRequest($actor, $actor->clinic, $kind));
        abort_if($kinds->isEmpty(), 403);
        $reports = ClinicReportExport::query()->where('clinic_id', $actor->clinic_id)->where('requested_by_user_id', $actor->id)->whereIn('kind', $kinds)->latest('id')->paginate(20);

        return view('clinic-reports.index', compact('reports', 'kinds'));
    }

    public function store(Request $request, ClinicReportService $service): RedirectResponse|JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor->clinic_id !== null, 403);
        $data = $request->validate(['request_id' => ['required', 'uuid'], 'kind' => ['required', 'in:activity,queue,service,finance'],
            'range_from' => ['required', 'date_format:Y-m-d', 'after_or_equal:1000-01-02', 'before_or_equal:9999-12-30'],
            'range_until' => ['required', 'date_format:Y-m-d', 'after_or_equal:range_from', 'before_or_equal:9999-12-30'], 'expires_at' => ['required', 'date_format:Y-m-d\TH:i']]);
        $report = $service->request($actor->clinic, $actor, $data);
        $url = route('clinic-reports.show', $report);

        return $request->expectsJson() ? response()->json(['message' => 'Laporan masuk antrean.', 'redirect' => $url]) : redirect($url)->with('status', 'Laporan masuk antrean.');
    }

    public function show(Request $request, ClinicReportExport $report, ClinicReportService $service): View
    {
        abort_unless($service->canRead($request->user(), $report), 403);

        return view('clinic-reports.show', compact('report'));
    }

    public function download(Request $request, ClinicReportExport $report, ClinicReportService $service, AuditLogger $audit): StreamedResponse
    {
        abort_unless($service->canRead($request->user(), $report), 403);
        abort_if($report->expires_at->lessThanOrEqualTo(now()), 410, 'Masa unduh laporan berakhir. Buat laporan baru bila masih diperlukan.');
        abort_unless($report->status === 'READY' && $report->file_path === $service->path($report) && Storage::disk('local')->exists($report->file_path), 404, 'Laporan belum siap diunduh.');
        abort_unless(hash_equals($report->file_hash, hash('sha256', Storage::disk('local')->get($report->file_path))), 409, 'Integritas berkas laporan perlu ditinjau.');
        $audit->recordForClinic($report->clinic, 'clinic_report.downloaded', $report, ['kind' => $report->kind, 'definition_version' => $report->definition_version], $request->user());

        return Storage::disk('local')->download($report->file_path, 'clinic-report-'.$report->kind.'-'.$report->id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}

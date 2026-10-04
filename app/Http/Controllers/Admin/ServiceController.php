<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Models\Service;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Service::class);

        $services = Service::query()
            ->where('clinic_id', request()->user()->clinic_id)
            ->withCount('doctors')
            ->orderBy('name')
            ->paginate(15);

        return view('admin.services.index', compact('services'));
    }

    public function create(): View
    {
        $this->authorize('create', Service::class);

        return view('admin.services.form');
    }

    public function store(StoreServiceRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $service = Service::query()->create($request->validated() + [
            'clinic_id' => $request->user()->clinic_id,
        ]);
        $auditLogger->record($request->user(), 'service.created', $service);

        return redirect()->route('admin.services.index')
            ->with('status', 'Layanan berhasil ditambahkan.');
    }

    public function edit(Service $service): View
    {
        $this->authorize('update', $service);

        return view('admin.services.form', compact('service'));
    }

    public function update(
        UpdateServiceRequest $request,
        Service $service,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $service->update($request->validated());
        $auditLogger->record($request->user(), 'service.updated', $service);

        return redirect()->route('admin.services.index')
            ->with('status', 'Layanan berhasil diperbarui.');
    }
}

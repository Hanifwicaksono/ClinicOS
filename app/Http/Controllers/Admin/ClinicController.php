<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClinicRequest;
use App\Http\Requests\UpdateClinicRequest;
use App\Models\Clinic;
use App\Services\AuditLogger;
use App\Services\ClinicRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ClinicController extends Controller
{
    public function index(): RedirectResponse
    {
        $clinic = request()->user()->clinic;

        return $clinic === null
            ? redirect()->route('admin.clinic.create')
            : redirect()->route('admin.clinic.edit', $clinic);
    }

    public function create(): View
    {
        $this->authorize('create', Clinic::class);

        return view('admin.clinic.form');
    }

    public function store(StoreClinicRequest $request, ClinicRegistrationService $clinicRegistrationService): RedirectResponse
    {
        $clinic = $clinicRegistrationService->create(
            $request->user(),
            $request->validated(),
            $request->file('logo'),
        );

        return redirect()->route('admin.clinic.edit', $clinic)
            ->with('status', 'Profil klinik berhasil dibuat.');
    }

    public function edit(Clinic $clinic): View
    {
        $this->authorize('update', $clinic);

        return view('admin.clinic.form', ['clinic' => $clinic->load('settings')]);
    }

    public function update(
        UpdateClinicRequest $request,
        Clinic $clinic,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $clinic, $auditLogger): void {
            $validated = $request->validated();
            $clinicData = Arr::except($validated, [
                'logo', 'opening_time', 'closing_time', 'timezone', 'queue_prefix',
            ]);

            if ($request->hasFile('logo')) {
                if ($clinic->logo_path !== null) {
                    Storage::disk('public')->delete($clinic->logo_path);
                }

                $clinicData['logo_path'] = $request->file('logo')->store('clinic-logos', 'public');
            }

            $clinic->update($clinicData);
            $clinic->settings()->updateOrCreate(
                ['clinic_id' => $clinic->id],
                Arr::only($validated, ['opening_time', 'closing_time', 'timezone', 'queue_prefix']),
            );
            $auditLogger->record($request->user(), 'clinic.updated', $clinic);
        });

        return back()->with('status', 'Profil klinik berhasil diperbarui.');
    }
}

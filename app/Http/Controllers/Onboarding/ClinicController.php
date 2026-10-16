<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOnboardingClinicRequest;
use App\Models\Clinic;
use App\Services\ClinicRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ClinicController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (request()->user()->clinic_id !== null) {
            return redirect()->route('onboarding.plans.index');
        }

        $this->authorize('create', Clinic::class);

        return view('admin.clinic.form', ['onboarding' => true]);
    }

    public function store(
        StoreOnboardingClinicRequest $request,
        ClinicRegistrationService $clinicRegistrationService,
    ): RedirectResponse {
        if ($request->user()->clinic_id !== null) {
            return redirect()->route('onboarding.plans.index');
        }

        $clinicRegistrationService->create(
            $request->user(),
            $request->validated(),
            $request->file('logo'),
        );

        return redirect()->route('onboarding.plans.index')
            ->with('status', 'Profil klinik berhasil dibuat. Pilih paket untuk mengaktifkan akses.');
    }
}

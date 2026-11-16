<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStaffRequest;
use App\Http\Requests\Admin\UpdateStaffRequest;
use App\Models\RealtimeRevocation;
use App\Models\Service;
use App\Models\User;
use App\Services\StaffAccountService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        $staffMembers = User::query()
            ->where('clinic_id', request()->user()->clinic_id)
            ->role(['Doctor', 'Receptionist'])
            ->with(['roles', 'doctor'])
            ->latest()
            ->paginate(12);

        $realtimeRevocations = RealtimeRevocation::where('clinic_id', request()->user()->clinic_id)
            ->whereRaw('auth_version = (select max(latest.auth_version) from realtime_revocations latest where latest.user_id = realtime_revocations.user_id)')
            ->latest()->limit(20)->get();

        return view('admin.staff.index', compact('staffMembers', 'realtimeRevocations'));
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.staff.form', [
            'services' => $this->clinicServices(),
        ]);
    }

    public function store(StoreStaffRequest $request, StaffAccountService $service): RedirectResponse
    {
        $service->create($request->user(), $request->validated());

        return redirect()->route('admin.staff.index')
            ->with('status', 'Akun staf berhasil dibuat. Staf wajib memverifikasi emailnya dan dapat meminta ulang tautan melalui halaman verifikasi jika belum diterima.');
    }

    public function edit(User $staff): View
    {
        $this->authorize('update', $staff);

        return view('admin.staff.form', [
            'staff' => $staff->load('doctor.services', 'roles'),
            'services' => $this->clinicServices(),
        ]);
    }

    public function update(
        UpdateStaffRequest $request,
        User $staff,
        StaffAccountService $service,
    ): RedirectResponse {
        $updatedStaff = $service->update($request->user(), $staff, $request->validated());
        $message = 'Data staf berhasil diperbarui.';
        if ($updatedStaff->wasChanged('email')) {
            $message .= ' Alamat email baru wajib diverifikasi. Staf dapat meminta ulang tautan melalui halaman verifikasi email.';
        }

        return redirect()->route('admin.staff.index')
            ->with('status', $message);
    }

    /** @return Collection<int, Service> */
    private function clinicServices(): Collection
    {
        return Service::query()
            ->where('clinic_id', request()->user()->clinic_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}

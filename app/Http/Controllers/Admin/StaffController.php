<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStaffRequest;
use App\Http\Requests\Admin\UpdateStaffRequest;
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

        return view('admin.staff.index', compact('staffMembers'));
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
            ->with('status', 'Akun staf berhasil dibuat. Tautan aktivasi telah dikirim ke email staf.');
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
        $service->update($request->user(), $staff, $request->validated());

        return redirect()->route('admin.staff.index')
            ->with('status', 'Data staf berhasil diperbarui.');
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

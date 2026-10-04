<?php

use App\Http\Controllers\Admin\ClinicController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DoctorScheduleController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Doctor\DashboardController as DoctorDashboardController;
use App\Http\Controllers\Patient\DashboardController as PatientDashboardController;
use App\Http\Controllers\Receptionist\DashboardController as ReceptionistDashboardController;
use App\Http\Middleware\RedirectToDashboard;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified', RedirectToDashboard::class])
    ->name('dashboard');

Route::middleware(['auth', 'verified', 'permission:dashboard.view'])->group(function () {
    Route::get('admin/dashboard', [AdminDashboardController::class, 'index'])
        ->middleware('role:Clinic Admin')->name('admin.dashboard');
    Route::get('doctor/dashboard', [DoctorDashboardController::class, 'index'])
        ->middleware('role:Doctor')->name('doctor.dashboard');
    Route::get('receptionist/dashboard', [ReceptionistDashboardController::class, 'index'])
        ->middleware('role:Receptionist')->name('receptionist.dashboard');
    Route::get('patient/dashboard', [PatientDashboardController::class, 'index'])
        ->middleware('role:Patient')->name('patient.dashboard');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', 'role:Clinic Admin'])->group(function () {
    Route::resource('clinic', ClinicController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::resource('staff', StaffController::class)->except(['show', 'destroy']);
    Route::resource('services', ServiceController::class)->except(['show', 'destroy']);
    Route::resource('doctor-schedules', DoctorScheduleController::class)
        ->parameters(['doctor-schedules' => 'doctor_schedule'])
        ->except(['show', 'destroy']);
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';

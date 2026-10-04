<?php

use App\Http\Controllers\Admin\ClinicController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DoctorScheduleController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Doctor\DashboardController as DoctorDashboardController;
use App\Http\Controllers\Patient\AppointmentController as PatientAppointmentController;
use App\Http\Controllers\Patient\DashboardController as PatientDashboardController;
use App\Http\Controllers\PublicBookingController;
use App\Http\Controllers\PublicClinicController;
use App\Http\Controllers\Receptionist\AppointmentController as ReceptionistAppointmentController;
use App\Http\Controllers\Receptionist\DashboardController as ReceptionistDashboardController;
use App\Http\Controllers\Receptionist\PatientController as ReceptionistPatientController;
use App\Http\Middleware\RedirectToDashboard;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::get('clinic/{clinic:slug}', [PublicClinicController::class, 'show'])->name('public.clinic.show');
Route::get('clinic/{clinic:slug}/booking', [PublicBookingController::class, 'create'])->name('public.booking.create');
Route::post('clinic/{clinic:slug}/booking', [PublicBookingController::class, 'store'])
    ->middleware('throttle:10,1')->name('public.booking.store');
Route::get('booking/{appointment}/status', [PublicBookingController::class, 'status'])
    ->middleware('throttle:30,1')->name('public.booking.status');
Route::post('booking/{appointment}/cancel', [PublicBookingController::class, 'cancel'])
    ->middleware('throttle:10,1')->name('public.booking.cancel');

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

Route::prefix('receptionist')->name('receptionist.')->middleware(['auth', 'verified', 'role:Clinic Admin|Receptionist'])->group(function () {
    Route::resource('patients', ReceptionistPatientController::class)->except(['destroy']);
    Route::resource('appointments', ReceptionistAppointmentController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('appointments/{appointment}/cancel', [ReceptionistAppointmentController::class, 'cancel'])->name('appointments.cancel');
});

Route::prefix('patient')->name('patient.')->middleware(['auth', 'verified', 'role:Patient'])->group(function () {
    Route::resource('appointments', PatientAppointmentController::class)->only(['index', 'show']);
    Route::post('appointments/{appointment}/cancel', [PatientAppointmentController::class, 'cancel'])->name('appointments.cancel');
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';

<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ClinicController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DoctorScheduleController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Doctor\DashboardController as DoctorDashboardController;
use App\Http\Controllers\Doctor\MedicalRecordController as DoctorMedicalRecordController;
use App\Http\Controllers\Doctor\VisitController as DoctorVisitController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Patient\AppointmentController as PatientAppointmentController;
use App\Http\Controllers\Patient\DashboardController as PatientDashboardController;
use App\Http\Controllers\PublicBookingController;
use App\Http\Controllers\PublicClinicController;
use App\Http\Controllers\PublicQueueController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\Receptionist\AppointmentController as ReceptionistAppointmentController;
use App\Http\Controllers\Receptionist\DashboardController as ReceptionistDashboardController;
use App\Http\Controllers\Receptionist\PatientController as ReceptionistPatientController;
use App\Http\Middleware\RedirectToDashboard;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::get('clinic/{clinic:slug}', [PublicClinicController::class, 'show'])->name('public.clinic.show');
Route::get('clinic/{clinic:slug}/queue', [PublicQueueController::class, 'index'])
    ->middleware('throttle:60,1')->name('public.queue.index');
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
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::resource('clinic', ClinicController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::resource('staff', StaffController::class)->except(['show', 'destroy']);
    Route::resource('services', ServiceController::class)->except(['show', 'destroy']);
    Route::resource('doctor-schedules', DoctorScheduleController::class)
        ->parameters(['doctor-schedules' => 'doctor_schedule'])
        ->except(['show', 'destroy']);
});

Route::prefix('notifications')->name('notifications.')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::post('read-all', [NotificationController::class, 'readAll'])->name('read-all');
    Route::post('{notification}/read', [NotificationController::class, 'read'])->name('read');
});

Route::prefix('receptionist')->name('receptionist.')->middleware(['auth', 'verified', 'role:Clinic Admin|Receptionist'])->group(function () {
    Route::resource('patients', ReceptionistPatientController::class)->except(['destroy']);
    Route::resource('appointments', ReceptionistAppointmentController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('appointments/{appointment}/cancel', [ReceptionistAppointmentController::class, 'cancel'])->name('appointments.cancel');
    Route::get('queues', [QueueController::class, 'index'])->name('queues.index');
});

Route::prefix('doctor')->name('doctor.')->middleware(['auth', 'verified', 'role:Doctor'])->group(function () {
    Route::get('queues', [QueueController::class, 'index'])->name('queues.index');
    Route::get('medical-records', [DoctorMedicalRecordController::class, 'index'])->name('medical-records.index');
    Route::post('queues/{queue}/start', [DoctorVisitController::class, 'start'])->name('visits.start');
    Route::get('visits/{visit}', [DoctorVisitController::class, 'show'])->name('visits.show');
    Route::put('visits/{visit}/draft', [DoctorVisitController::class, 'save'])->name('visits.save');
    Route::put('visits/{visit}/complete', [DoctorVisitController::class, 'complete'])->name('visits.complete');
    Route::put('visits/{visit}/correct', [DoctorVisitController::class, 'correct'])->name('visits.correct');
});

Route::prefix('queues')->name('queues.')->middleware(['auth', 'verified', 'role:Clinic Admin|Receptionist|Doctor'])->group(function () {
    Route::post('{queue}/check-in', [QueueController::class, 'checkIn'])->name('check-in');
    Route::post('{queue}/call', [QueueController::class, 'call'])->name('call');
    Route::post('{queue}/skip', [QueueController::class, 'skip'])->name('skip');
    Route::post('{queue}/return', [QueueController::class, 'returnToWaiting'])->name('return');
    Route::post('{queue}/cancel', [QueueController::class, 'cancel'])->name('cancel');
    Route::post('{queue}/no-show', [QueueController::class, 'markNoShow'])->name('no-show');
});

Route::prefix('patient')->name('patient.')->middleware(['auth', 'verified', 'role:Patient'])->group(function () {
    Route::resource('appointments', PatientAppointmentController::class)->only(['index', 'show']);
    Route::post('appointments/{appointment}/cancel', [PatientAppointmentController::class, 'cancel'])->name('appointments.cancel');
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';

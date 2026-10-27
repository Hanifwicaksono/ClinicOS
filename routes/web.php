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
use App\Http\Controllers\DokuNotificationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Onboarding\ClinicController as OnboardingClinicController;
use App\Http\Controllers\Onboarding\PlanController as OnboardingPlanController;
use App\Http\Controllers\Patient\AppointmentController as PatientAppointmentController;
use App\Http\Controllers\Patient\DashboardController as PatientDashboardController;
use App\Http\Controllers\Platform\DashboardController as PlatformDashboardController;
use App\Http\Controllers\Platform\PaymentController as PlatformPaymentController;
use App\Http\Controllers\Platform\SubscriptionGrantController as PlatformSubscriptionGrantController;
use App\Http\Controllers\Platform\SubscriptionPlanController as PlatformSubscriptionPlanController;
use App\Http\Controllers\Platform\TenantController as PlatformTenantController;
use App\Http\Controllers\PublicBookingController;
use App\Http\Controllers\PublicClinicController;
use App\Http\Controllers\PublicQueueController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\Receptionist\AppointmentController as ReceptionistAppointmentController;
use App\Http\Controllers\Receptionist\DashboardController as ReceptionistDashboardController;
use App\Http\Controllers\Receptionist\PatientController as ReceptionistPatientController;
use App\Http\Controllers\SubscriptionPaymentController;
use App\Http\Middleware\RedirectToDashboard;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $minimumPrices = SubscriptionPlan::query()
        ->whereIn('code', ['individual', 'team'])
        ->where('is_active', true)
        ->with(['prices' => fn (HasMany $query): HasMany => $query->where('is_active', true)])
        ->get()
        ->mapWithKeys(fn (SubscriptionPlan $plan): array => [$plan->code => $plan->prices->min('amount')]);

    return view('welcome', [
        'statistics' => [
            'clinics' => Clinic::query()->where('is_active', true)->count(),
            'patients' => Patient::withoutGlobalScopes()->where('is_active', true)->count(),
        ],
        'minimumPrices' => $minimumPrices,
    ]);
})->name('welcome');

Route::post('payments/doku/notification', DokuNotificationController::class)
    ->middleware('throttle:doku-notification')
    ->name('doku.notifications');

Route::get('clinic/{clinic:slug}', [PublicClinicController::class, 'show'])->name('public.clinic.show');
Route::get('clinic/{clinic:slug}/queue', [PublicQueueController::class, 'index'])
    ->middleware('throttle:60,1')->name('public.queue.index');
Route::get('clinic/{clinic:slug}/booking', [PublicBookingController::class, 'create'])
    ->middleware(['auth', 'verified', 'role:Patient'])->name('public.booking.create');
Route::post('clinic/{clinic:slug}/booking', [PublicBookingController::class, 'store'])
    ->middleware(['auth', 'verified', 'role:Patient', 'subscription.writable', 'throttle:10,1'])->name('public.booking.store');
Route::get('booking/{appointment}/status', [PublicBookingController::class, 'status'])
    ->middleware('throttle:30,1')->name('public.booking.status');
Route::post('booking/{appointment}/cancel', [PublicBookingController::class, 'cancel'])
    ->middleware(['subscription.writable', 'throttle:10,1'])->name('public.booking.cancel');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified', RedirectToDashboard::class])
    ->name('dashboard');

Route::middleware(['auth', 'verified', 'onboarding.complete', 'permission:dashboard.view'])->group(function () {
    Route::get('admin/dashboard', [AdminDashboardController::class, 'index'])
        ->middleware('role:Clinic Admin')->name('admin.dashboard');
    Route::get('doctor/dashboard', [DoctorDashboardController::class, 'index'])
        ->middleware('role:Doctor')->name('doctor.dashboard');
    Route::get('receptionist/dashboard', [ReceptionistDashboardController::class, 'index'])
        ->middleware('role:Receptionist')->name('receptionist.dashboard');
    Route::get('patient/dashboard', [PatientDashboardController::class, 'index'])
        ->middleware('role:Patient')->name('patient.dashboard');
});

Route::prefix('onboarding')->name('onboarding.')->middleware(['auth', 'verified', 'clinic_owner'])->group(function () {
    Route::get('clinic', [OnboardingClinicController::class, 'create'])->name('clinic.create');
    Route::post('clinic', [OnboardingClinicController::class, 'store'])->name('clinic.store');
    Route::get('plans', [OnboardingPlanController::class, 'index'])->name('plans.index');
    Route::post('plans/free', [OnboardingPlanController::class, 'activateFree'])->name('plans.free');
    Route::post('plans/trial', [OnboardingPlanController::class, 'startTrial'])->name('plans.trial');
    Route::post('plans/paid/{subscriptionPrice}', [OnboardingPlanController::class, 'selectPaid'])
        ->middleware('throttle:checkout')->name('plans.paid');
});

Route::prefix('subscription')->name('subscription.')->middleware(['auth', 'verified', 'clinic_owner', 'throttle:payment-status'])->group(function () {
    Route::get('payments', [SubscriptionPaymentController::class, 'index'])->name('payments.index');
    Route::get('payments/{payment}', [SubscriptionPaymentController::class, 'show'])->name('payments.show');
    Route::post('payments/{payment}/reconcile', [SubscriptionPaymentController::class, 'reconcile'])
        ->name('payments.reconcile');
});

Route::prefix('platform')->name('platform.')->middleware(['auth', 'verified', 'role:Platform Admin'])->group(function () {
    Route::get('dashboard', [PlatformDashboardController::class, 'index'])
        ->middleware('permission:platform.dashboard.view')->name('dashboard');
    Route::get('tenants', [PlatformTenantController::class, 'index'])
        ->middleware('permission:platform.tenant.view')->name('tenants.index');
    Route::get('tenants/{clinic}', [PlatformTenantController::class, 'show'])
        ->middleware('permission:platform.tenant.view')->name('tenants.show');
    Route::get('subscription-plans', [PlatformSubscriptionPlanController::class, 'index'])
        ->middleware('permission:platform.plan.manage')->name('subscription-plans.index');
    Route::patch('subscription-plans/{subscriptionPlan}', [PlatformSubscriptionPlanController::class, 'updatePlan'])
        ->middleware('permission:platform.plan.manage')->name('subscription-plans.update');
    Route::patch('subscription-prices/{subscriptionPrice}', [PlatformSubscriptionPlanController::class, 'updatePrice'])
        ->middleware('permission:platform.plan.manage')->name('subscription-prices.update');
    Route::post('tenants/{clinic}/subscription-grants', [PlatformSubscriptionGrantController::class, 'store'])
        ->middleware('permission:platform.subscription.grant')->name('subscription-grants.store');
    Route::get('payments', [PlatformPaymentController::class, 'index'])
        ->middleware('permission:platform.payment.view')->name('payments.index');
    Route::get('payments/{payment}', [PlatformPaymentController::class, 'show'])
        ->middleware(['permission:platform.payment.view', 'throttle:payment-status'])->name('payments.show');
    Route::post('payments/{payment}/reconcile', [PlatformPaymentController::class, 'reconcile'])
        ->middleware(['permission:platform.payment.manage', 'throttle:payment-status'])->name('payments.reconcile');
    Route::post('payments/{payment}/refund', [PlatformPaymentController::class, 'refund'])
        ->middleware('permission:platform.payment.manage')->name('payments.refund');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', 'onboarding.complete', 'role:Clinic Admin', 'subscription.writable'])->group(function () {
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::resource('clinic', ClinicController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::resource('staff', StaffController::class)->except(['show', 'destroy']);
    Route::resource('services', ServiceController::class)->except(['show', 'destroy']);
    Route::resource('doctor-schedules', DoctorScheduleController::class)
        ->parameters(['doctor-schedules' => 'doctor_schedule'])
        ->except(['show', 'destroy']);
});

Route::prefix('notifications')->name('notifications.')->middleware(['auth', 'verified', 'onboarding.complete'])->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::post('read-all', [NotificationController::class, 'readAll'])->name('read-all');
    Route::post('{notification}/read', [NotificationController::class, 'read'])->name('read');
});

Route::prefix('receptionist')->name('receptionist.')->middleware(['auth', 'verified', 'onboarding.complete', 'role:Clinic Admin|Receptionist', 'subscription.writable'])->group(function () {
    Route::get('patients/search', [ReceptionistPatientController::class, 'search'])->name('patients.search');
    Route::resource('patients', ReceptionistPatientController::class)->except(['destroy']);
    Route::resource('appointments', ReceptionistAppointmentController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('appointments/{appointment}/cancel', [ReceptionistAppointmentController::class, 'cancel'])->name('appointments.cancel');
    Route::get('queues', [QueueController::class, 'index'])->name('queues.index');
});

Route::prefix('doctor')->name('doctor.')->middleware(['auth', 'verified', 'onboarding.complete', 'role:Doctor', 'subscription.writable'])->group(function () {
    Route::get('queues', [QueueController::class, 'index'])->name('queues.index');
    Route::get('medical-records', [DoctorMedicalRecordController::class, 'index'])->name('medical-records.index');
    Route::post('queues/{queue}/start', [DoctorVisitController::class, 'start'])->name('visits.start');
    Route::get('visits/{visit}', [DoctorVisitController::class, 'show'])->name('visits.show');
    Route::get('visits/{visit}/photos/{photo}', [DoctorVisitController::class, 'photo'])
        ->whereNumber('photo')->name('visits.photos.show');
    Route::put('visits/{visit}/draft', [DoctorVisitController::class, 'save'])->name('visits.save');
    Route::put('visits/{visit}/complete', [DoctorVisitController::class, 'complete'])->name('visits.complete');
    Route::put('visits/{visit}/correct', [DoctorVisitController::class, 'correct'])->name('visits.correct');
});

Route::prefix('queues')->name('queues.')->middleware(['auth', 'verified', 'onboarding.complete', 'role:Clinic Admin|Receptionist|Doctor', 'subscription.writable'])->group(function () {
    Route::post('{queue}/check-in', [QueueController::class, 'checkIn'])->name('check-in');
    Route::post('{queue}/call', [QueueController::class, 'call'])->name('call');
    Route::post('{queue}/skip', [QueueController::class, 'skip'])->name('skip');
    Route::post('{queue}/return', [QueueController::class, 'returnToWaiting'])->name('return');
    Route::post('{queue}/cancel', [QueueController::class, 'cancel'])->name('cancel');
    Route::post('{queue}/no-show', [QueueController::class, 'markNoShow'])->name('no-show');
});

Route::prefix('patient')->name('patient.')->middleware(['auth', 'verified', 'onboarding.complete', 'role:Patient', 'subscription.writable'])->group(function () {
    Route::resource('appointments', PatientAppointmentController::class)->only(['index', 'show']);
    Route::post('appointments/{appointment}/cancel', [PatientAppointmentController::class, 'cancel'])->name('appointments.cancel');
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';

<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ClinicController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DoctorScheduleController;
use App\Http\Controllers\Admin\RealtimeRevocationController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\AppointmentReplacementController;
use App\Http\Controllers\CareContinuityController;
use App\Http\Controllers\ClinicalDocumentController;
use App\Http\Controllers\ClinicReportController;
use App\Http\Controllers\Doctor\DashboardController as DoctorDashboardController;
use App\Http\Controllers\Doctor\MedicalRecordController as DoctorMedicalRecordController;
use App\Http\Controllers\Doctor\VisitController as DoctorVisitController;
use App\Http\Controllers\Doctor\VoiceSessionController;
use App\Http\Controllers\DoctorAbsenceController;
use App\Http\Controllers\DokuNotificationController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Onboarding\ClinicController as OnboardingClinicController;
use App\Http\Controllers\Onboarding\PlanController as OnboardingPlanController;
use App\Http\Controllers\Patient\AppointmentController as PatientAppointmentController;
use App\Http\Controllers\Patient\DashboardController as PatientDashboardController;
use App\Http\Controllers\PatientBillingController;
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
use App\Http\Controllers\SubscriptionAddonController;
use App\Http\Controllers\SubscriptionPaymentController;
use App\Http\Middleware\RedirectToDashboard;
use App\Models\AddonPrice;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

$baseDomain = parse_url(config('app.url'), PHP_URL_HOST);
$clinicDomain = filter_var($baseDomain, FILTER_VALIDATE_IP) ? 'localhost' : $baseDomain;

Route::domain('{clinic:slug}.'.$clinicDomain)->group(function (): void {
    Route::get('/', [PublicClinicController::class, 'show'])->name('public.clinic.show');
    Route::get('queue', [PublicQueueController::class, 'index'])
        ->middleware('throttle:60,1')->name('public.queue.index');
});

if (filter_var($baseDomain, FILTER_VALIDATE_IP)) {
    Route::domain($baseDomain)->prefix('clinic/{clinic}')->group(function (): void {
        Route::get('/', fn (string $clinic): RedirectResponse => redirect()->route('public.clinic.show', ['clinic' => $clinic]));
        Route::get('queue', fn (string $clinic): RedirectResponse => redirect()->route('public.queue.index', ['clinic' => $clinic]));
    });
}

Route::domain($baseDomain)->group(function (): void {
    if (app()->environment(['local', 'testing'])) {
        Route::view('previews/visit-summary', 'previews.visit-summary')
            ->name('previews.visit-summary');
    }

    Route::get('/', function () {
        $monthlyPrices = SubscriptionPlan::query()
            ->whereIn('code', ['individual', 'team'])
            ->where('is_active', true)
            ->with(['prices' => fn (HasMany $query): HasMany => $query->where('is_active', true)->where('duration_months', 1)])
            ->get()
            ->mapWithKeys(fn (SubscriptionPlan $plan): array => [$plan->code => $plan->prices->first()?->amount]);

        return view('welcome', [
            'monthlyPrices' => $monthlyPrices,
            'addonMonthlyPrice' => AddonPrice::query()->where('code', 'odontogram')->where('is_active', true)->value('monthly_amount'),
        ]);
    })->name('welcome');

    Route::get('display/{clinicSlugOrToken}', [PublicQueueController::class, 'display'])
        ->middleware('throttle:60,1')
        ->name('public.queue.display');

    Route::post('payments/doku/notification', DokuNotificationController::class)
        ->middleware('throttle:doku-notification')
        ->name('doku.notifications');

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
        Route::get('addons', [SubscriptionAddonController::class, 'index'])->middleware('onboarding.complete')->name('addons.index');
        Route::post('addons/odontogram', [SubscriptionAddonController::class, 'store'])
            ->middleware(['onboarding.complete', 'throttle:checkout'])->name('addons.store');
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
        Route::patch('addon-prices/{addonPrice}', [PlatformSubscriptionPlanController::class, 'updateAddon'])
            ->middleware('permission:platform.plan.manage')->name('addon-prices.update');
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
        Route::post('realtime-revocations/{revocation}/retry', [RealtimeRevocationController::class, 'retry'])
            ->withoutMiddleware('subscription.writable')->name('realtime-revocations.retry');
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
        Route::post('patients/duplicates', [ReceptionistPatientController::class, 'duplicates'])->middleware('throttle:30,1')->name('patients.duplicates');
        Route::resource('patients', ReceptionistPatientController::class)->except(['destroy']);
        Route::resource('appointments', ReceptionistAppointmentController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('appointments/{appointment}/cancel', [ReceptionistAppointmentController::class, 'cancel'])->name('appointments.cancel');
        Route::get('queues', [QueueController::class, 'index'])->name('queues.index');
    });

    Route::middleware(['auth', 'verified', 'onboarding.complete', 'role:Clinic Admin|Receptionist', 'subscription.writable'])->group(function () {
        Route::get('appointments/{appointment}/replace', [AppointmentReplacementController::class, 'create'])->name('appointments.replace.create');
        Route::post('appointments/{appointment}/replace/preview', [AppointmentReplacementController::class, 'preview'])->name('appointments.replace.preview');
        Route::post('appointments/{appointment}/replace', [AppointmentReplacementController::class, 'store'])->name('appointments.replace.store');
    });
    Route::prefix('doctor-absences')->name('doctor-absences.')->middleware(['auth', 'verified', 'onboarding.complete', 'role:Clinic Admin|Receptionist|Doctor', 'subscription.writable'])->group(function () {
        Route::get('/', [DoctorAbsenceController::class, 'index'])->name('index');
        Route::post('/', [DoctorAbsenceController::class, 'store'])->name('store');
        Route::get('{absence}', [DoctorAbsenceController::class, 'show'])->name('show');
        Route::post('{absence}/{action}', [DoctorAbsenceController::class, 'change'])->whereIn('action', ['verify', 'request-cancel', 'reopen'])->name('change');
        Route::post('{absence}/appointments/{appointment}/contact', [DoctorAbsenceController::class, 'contact'])->name('contact');
    });

    Route::prefix('clinic-reports')->name('clinic-reports.')->middleware(['auth', 'verified', 'onboarding.complete', 'role:Clinic Admin|Receptionist', 'subscription.writable'])->group(function (): void {
        Route::get('/', [ClinicReportController::class, 'index'])->name('index');
        Route::post('/', [ClinicReportController::class, 'store'])->name('store');
        Route::get('{report}', [ClinicReportController::class, 'show'])->name('show');
        Route::get('{report}/download', [ClinicReportController::class, 'download'])->name('download');
    });

    Route::prefix('patient-billing')->name('patient-billing.')->middleware(['auth', 'verified', 'onboarding.complete', 'role:Clinic Admin|Receptionist|Doctor|Patient', 'subscription.writable'])->group(function (): void {
        Route::get('/', [PatientBillingController::class, 'index'])->name('index');
        Route::post('appointments/{appointment}/open', [PatientBillingController::class, 'open'])->name('open');
        Route::get('visits/{visit}/clinical-review', [PatientBillingController::class, 'clinicalForm'])->name('clinical-form');
        Route::post('visits/{visit}/clinical-review', [PatientBillingController::class, 'clinicalReview'])->name('clinical-review');
        Route::get('closings', [PatientBillingController::class, 'closings'])->name('closings.index');
        Route::post('closings', [PatientBillingController::class, 'close'])->name('closings.store');
        Route::get('closings/{closing}', [PatientBillingController::class, 'closing'])->name('closings.show');
        Route::post('closings/{closing}/{action}', [PatientBillingController::class, 'reviewClosing'])->whereIn('action', ['approve', 'reject'])->name('closings.review');
        Route::get('bills/{bill}', [PatientBillingController::class, 'show'])->name('show');
        Route::post('bills/{bill}/transactions', [PatientBillingController::class, 'receive'])->name('receive');
        Route::post('bills/{bill}/requests', [PatientBillingController::class, 'propose'])->name('propose');
        Route::post('bills/{bill}/correction', [PatientBillingController::class, 'correct'])->name('correct');
        Route::post('transactions/{transaction}/{action}', [PatientBillingController::class, 'transaction'])->whereIn('action', ['verify', 'fail'])->name('transaction');
        Route::post('requests/{proposal}/decide/{action}', [PatientBillingController::class, 'decide'])->whereIn('action', ['approve', 'reject'])->name('decide');
        Route::post('requests/{proposal}/refund', [PatientBillingController::class, 'refund'])->name('refund');
        Route::post('documents/{document}/print', [PatientBillingController::class, 'printDocument'])->name('documents.print');
    });

    Route::prefix('clinical-documents')->name('clinical-documents.')->middleware(['auth', 'verified', 'onboarding.complete', 'role:Doctor|Receptionist|Patient', 'subscription.writable'])->group(function (): void {
        Route::get('/', [ClinicalDocumentController::class, 'index'])->name('index');
        Route::get('visits/{visit}', [ClinicalDocumentController::class, 'forVisit'])->name('for-visit');
        Route::get('visits/{visit}/preview', [ClinicalDocumentController::class, 'preview'])->name('preview');
        Route::post('visits/{visit}/issue', [ClinicalDocumentController::class, 'issue'])->name('issue');
        Route::get('{document}', [ClinicalDocumentController::class, 'show'])->whereNumber('document')->name('show');
        Route::post('{document}/print', [ClinicalDocumentController::class, 'print'])->name('print');
        Route::post('{document}/handover', [ClinicalDocumentController::class, 'handover'])->name('handover');
        Route::post('{document}/{action}', [ClinicalDocumentController::class, 'change'])->whereIn('action', ['release', 'recall'])->name('change');
    });
    Route::prefix('follow-ups')->name('follow-ups.')->middleware(['auth', 'verified', 'onboarding.complete', 'role:Clinic Admin|Receptionist|Doctor|Patient', 'subscription.writable'])->group(function (): void {
        Route::get('/', [FollowUpController::class, 'index'])->name('index');
        Route::get('visits/{visit}/create', [FollowUpController::class, 'create'])->name('create');
        Route::post('visits/{visit}', [FollowUpController::class, 'store'])->name('store');
        Route::get('{followUp}', [FollowUpController::class, 'show'])->whereNumber('followUp')->name('show');
        Route::post('{followUp}/book', [FollowUpController::class, 'book'])->name('book');
        Route::post('{followUp}/print', [FollowUpController::class, 'print'])->name('print');
        Route::post('{followUp}/{action}', [FollowUpController::class, 'change'])->whereIn('action', ['revise', 'transfer-request', 'transfer-accept', 'transfer-decline', 'decline', 'cancel', 'contact', 'complete'])->name('change');
    });

    Route::prefix('care-continuity')->name('care-continuity.')->middleware(['auth', 'verified', 'onboarding.complete', 'role:Clinic Admin|Receptionist|Doctor', 'subscription.writable'])->group(function (): void {
        Route::get('/', [CareContinuityController::class, 'index'])->name('index');
        Route::get('visits/{visit}', [CareContinuityController::class, 'show'])->name('show');
        Route::post('visits/{visit}/workflow/{action}', [CareContinuityController::class, 'workflow'])->whereIn('action', ['pause', 'resume', 'ready', 'clear-ready', 'interrupt', 'note'])->name('workflow');
        Route::post('visits/{visit}/leads', [CareContinuityController::class, 'appoint'])->name('leads.appoint');
        Route::post('visits/{visit}/leads/{lead}/revoke', [CareContinuityController::class, 'revoke'])->name('leads.revoke');
        Route::post('visits/{visit}/handoffs', [CareContinuityController::class, 'requestHandoff'])->name('handoffs.store');
        Route::get('handoffs/{continuation}', [CareContinuityController::class, 'handoff'])->name('handoffs.show');
        Route::post('handoffs/{continuation}/respond/{action}', [CareContinuityController::class, 'respond'])->whereIn('action', ['accept', 'decline', 'cancel'])->name('handoffs.respond');
        Route::post('handoffs/{continuation}/book', [CareContinuityController::class, 'book'])->name('handoffs.book');
        Route::post('tasks/{task}/{action}', [CareContinuityController::class, 'task'])->whereIn('action', ['status', 'resolve', 'cancel'])->name('tasks.change');
    });

    Route::prefix('doctor')->name('doctor.')->middleware(['auth', 'verified', 'onboarding.complete', 'role:Doctor', 'subscription.writable'])->group(function () {
        Route::get('queues', [QueueController::class, 'index'])->name('queues.index');
        Route::get('medical-records', [DoctorMedicalRecordController::class, 'index'])->name('medical-records.index');
        Route::post('queues/{queue}/start', [DoctorVisitController::class, 'start'])->name('visits.start');
        Route::get('visits/{visit}', [DoctorVisitController::class, 'show'])->name('visits.show');
        Route::prefix('visits/{visit}/voice')->name('voice.')->middleware('throttle:voice')->group(function (): void {
            Route::get('/', [VoiceSessionController::class, 'index'])->name('index');
            Route::post('/', [VoiceSessionController::class, 'store'])->name('store');
            Route::get('{voiceSession}', [VoiceSessionController::class, 'show'])->name('show');
            Route::post('{voiceSession}/segments', [VoiceSessionController::class, 'segment'])->name('segments');
            Route::post('{voiceSession}/{action}', [VoiceSessionController::class, 'change'])
                ->whereIn('action', ['pause', 'resume', 'stop', 'cancel', 'withdraw', 'retry', 'review', 'approve'])->name('change');
        });
        Route::get('visits/{visit}/photos/{photo}', [DoctorVisitController::class, 'photo'])
            ->whereNumber('photo')->name('visits.photos.show');
        Route::put('visits/{visit}/draft', [DoctorVisitController::class, 'save'])->name('visits.save');
        Route::post('visits/{visit}/allergies', [DoctorVisitController::class, 'storeAllergy'])->name('visits.allergies.store');
        Route::put('visits/{visit}/allergies/{allergy}', [DoctorVisitController::class, 'updateAllergy'])->name('visits.allergies.update');
        Route::put('visits/{visit}/complete', [DoctorVisitController::class, 'complete'])->name('visits.complete');
        Route::put('visits/{visit}/finalize-interruption', [DoctorVisitController::class, 'finalizeInterruption'])->name('visits.finalize-interruption');
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
        Route::resource('appointments', PatientAppointmentController::class)->only(['index', 'create', 'show']);
        Route::post('appointments/{appointment}/cancel', [PatientAppointmentController::class, 'cancel'])->name('appointments.cancel');
    });

    Route::post('notifications/preferences', [NotificationController::class, 'preferences'])->middleware(['auth', 'verified'])->name('notifications.preferences');

    Route::view('profile', 'profile')
        ->middleware(['auth'])
        ->name('profile');

    require __DIR__.'/auth.php';
});

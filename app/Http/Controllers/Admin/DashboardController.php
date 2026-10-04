<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Queue;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\QueueStatus;
use App\VisitStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the Clinic Admin dashboard.
     */
    public function index(Request $request): View
    {
        $clinic = $request->user()->clinic;
        $clinicId = $clinic?->id;
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $timezone = $clinic?->settings?->timezone ?? 'Asia/Jakarta';
        $from = CarbonImmutable::parse($validated['from'] ?? now($timezone)->toDateString(), $timezone)->startOfDay();
        $to = CarbonImmutable::parse($validated['to'] ?? now($timezone)->toDateString(), $timezone)->endOfDay();

        $metrics = [
            'doctors' => $clinicId === null ? 0 : Doctor::query()->where('clinic_id', $clinicId)->where('is_active', true)->count(),
            'receptionists' => $clinicId === null ? 0 : User::query()->where('clinic_id', $clinicId)->role('Receptionist')->where('is_active', true)->count(),
            'services' => $clinicId === null ? 0 : Service::query()->where('clinic_id', $clinicId)->where('is_active', true)->count(),
            'schedules' => $clinicId === null ? 0 : DoctorSchedule::query()
                ->whereHas('doctor', fn ($query) => $query->where('clinic_id', $clinicId))
                ->where('is_active', true)
                ->count(),
        ];

        $recentStaff = User::query()
            ->when($clinicId !== null, fn ($query) => $query->where('clinic_id', $clinicId))
            ->when($clinicId === null, fn ($query) => $query->whereRaw('1 = 0'))
            ->role(['Doctor', 'Receptionist'])
            ->with('roles')
            ->latest()
            ->limit(5)
            ->get();

        $appointmentQuery = Appointment::query()
            ->where('clinic_id', $clinicId)
            ->whereBetween('appointment_date', [$from->toDateString(), $to->toDateString()]);
        $completedVisitQuery = Visit::query()
            ->where('clinic_id', $clinicId)
            ->where('status', VisitStatus::Completed->value)
            ->whereBetween('completed_at', [$from->utc(), $to->utc()]);
        $operationalMetrics = [
            'unique_patients' => (clone $appointmentQuery)->distinct()->count('patient_id'),
            'bookings' => (clone $appointmentQuery)->count(),
            'completed_visits' => (clone $completedVisitQuery)->count(),
            'active_queues' => Queue::query()
                ->where('clinic_id', $clinicId)
                ->whereBetween('queue_date', [$from->toDateString(), $to->toDateString()])
                ->whereIn('status', [QueueStatus::Waiting->value, QueueStatus::Called->value, QueueStatus::InProgress->value])
                ->count(),
            'service_value' => (float) (clone $completedVisitQuery)->sum('total_amount'),
        ];
        $recentVisits = (clone $completedVisitQuery)
            ->with(['patient:id,name', 'doctor.user:id,name'])
            ->latest('completed_at')
            ->limit(6)
            ->get();

        return view('admin.dashboard', [
            'clinic' => $clinic,
            'metrics' => $metrics,
            'recentStaff' => $recentStaff,
            'operationalMetrics' => $operationalMetrics,
            'recentVisits' => $recentVisits,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'timezone' => $timezone,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use Illuminate\View\View;

class PublicClinicController extends Controller
{
    public function show(Clinic $clinic): View
    {
        abort_unless($clinic->is_active, 404);

        $clinic->load([
            'settings',
            'services' => fn ($query) => $query->where('is_active', true)->orderBy('name'),
            'doctors' => fn ($query) => $query
                ->where('is_active', true)
                ->whereHas('user', fn ($userQuery) => $userQuery->where('is_active', true))
                ->with([
                    'user:id,name',
                    'services' => fn ($serviceQuery) => $serviceQuery->where('is_active', true),
                    'schedules' => fn ($scheduleQuery) => $scheduleQuery->where('is_active', true)->orderBy('day_of_week')->orderBy('start_time'),
                ])
                ->orderBy('id'),
        ]);

        return view('public.clinic', compact('clinic'));
    }
}

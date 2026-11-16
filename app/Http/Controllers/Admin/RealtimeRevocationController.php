<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RevokeRealtimeAccess;
use App\Models\RealtimeRevocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RealtimeRevocationController extends Controller
{
    public function retry(Request $request, RealtimeRevocation $revocation): RedirectResponse
    {
        abort_unless($request->user()->clinic_id !== null && $request->user()->clinic_id === $revocation->clinic_id
            && $request->user()->canAny(['doctor.update', 'receptionist.update']), 403);
        if ($revocation->acknowledged_at === null) {
            RevokeRealtimeAccess::dispatch($revocation->id)->onConnection('database');
        }

        return back()->with('status', 'Permintaan pemeriksaan ulang revokasi realtime telah diterima. Hak HTTP tetap mengikuti akses terbaru.');
    }
}

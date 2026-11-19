<?php

namespace App\Http\Controllers;

use App\Models\PatientReminderDelivery;
use App\Notifications\PatientReminderNotification;
use App\Services\PatientReminderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $request->user()->notifications()->paginate(20);
        foreach ($notifications as $notification) {
            $notification->data = $this->currentData($notification, $request);
        }

        return view('notifications.index', compact('notifications'));
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $storedNotification = $request->user()->notifications()->findOrFail($notification);
        $storedNotification->markAsRead();
        $url = (string) ($this->currentData($storedNotification, $request)['url'] ?? route('notifications.index', absolute: false));

        return redirect(str_starts_with($url, '/') ? $url : route('notifications.index'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('status', 'Semua notifikasi ditandai sudah dibaca.');
    }

    public function preferences(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole('Patient'), 403);
        $data = $request->validate(['near_turn' => ['required', 'boolean'], 'appointment' => ['required', 'boolean'], 'follow_up' => ['required', 'boolean']]);
        $request->user()->forceFill(['reminder_preferences' => array_map(fn ($value) => (bool) $value, $data)])->save();

        return back()->with('status', 'Preferensi pengingat disimpan.');
    }

    private function currentData(DatabaseNotification $notification, Request $request): array
    {
        if ($notification->type !== PatientReminderNotification::class) {
            return $notification->data;
        }
        $delivery = PatientReminderDelivery::query()->where('recipient_user_id', $request->user()->id)->find($notification->data['reminder_delivery_id'] ?? 0);
        $context = $delivery === null ? null : app(PatientReminderService::class)->context($delivery);

        return $context['payload'] ?? ['title' => 'Pengingat terdahulu', 'message' => 'Pengingat ini sudah tidak berlaku. Periksa status terbaru pada daftar booking atau kontrol.', 'url' => route('notifications.index', absolute: false), 'status' => 'STALE'];
    }
}

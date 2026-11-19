<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class ClinicReportReadyNotification extends Notification
{
    public function __construct(public int $reportId) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => 'Laporan agregat siap', 'message' => 'Periksa periode dan unduh laporan selama masa berlakunya.', 'status' => 'REPORT_READY', 'url' => route('clinic-reports.show', $this->reportId, absolute: false)];
    }
}

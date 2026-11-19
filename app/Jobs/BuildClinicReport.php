<?php

namespace App\Jobs;

use App\Services\ClinicReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BuildClinicReport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $reportId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(ClinicReportService $reports): void
    {
        $reports->generate($this->reportId);
    }
}

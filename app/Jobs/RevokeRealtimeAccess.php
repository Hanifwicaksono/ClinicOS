<?php

namespace App\Jobs;

use App\Models\RealtimeRevocation;
use App\Services\Realtime\ControlChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Broadcast;
use RuntimeException;
use Throwable;

class RevokeRealtimeAccess implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public int $tries = 4;

    public function __construct(public string $revocationId) {}

    public function backoff(): array
    {
        return [1, 5, 30];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $revocation = RealtimeRevocation::findOrFail($this->revocationId);
        if ($revocation->acknowledged_at !== null) {
            return;
        }
        try {
            Broadcast::connection('reverb')->getPusher()->trigger(ControlChannel::NAME, 'access.revoke', ['request_id' => $revocation->id]);
            if ($revocation->fresh()->acknowledged_at === null) {
                throw new RuntimeException('No server delivery-fence acknowledgement.');
            }
        } catch (Throwable $exception) {
            RealtimeRevocation::whereKey($this->revocationId)->whereNull('acknowledged_at')
                ->update(['failed_at' => now(), 'error_code' => class_basename($exception), 'updated_at' => now()]);
            throw new RuntimeException('Realtime revocation is awaiting a verified single-node fence acknowledgement.');
        }
    }
}

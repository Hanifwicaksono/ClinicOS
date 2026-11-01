<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\MedicalRecord;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoActivitySeeder extends Seeder
{
    /**
     * Seed non-medical notification and audit activity for demonstrations.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $clinic = Clinic::query()->where('slug', 'klinik-sehat-sentosa')->first();

        if ($clinic === null) {
            return;
        }

        $users = User::query()
            ->whereIn('email', [
                'platform@clinicos.test',
                'admin@clinicos.test',
                'doctor@clinicos.test',
                'dentist@clinicos.test',
                'receptionist@clinicos.test',
                'patient@clinicos.test',
            ])
            ->get()
            ->keyBy('email');

        foreach ($this->notificationFixtures() as $fixture) {
            $user = $users->get($fixture['email']);

            if ($user === null) {
                continue;
            }

            $user->notifications()->updateOrCreate(
                ['id' => $fixture['id']],
                [
                    'type' => 'demo.activity',
                    'data' => [
                        'title' => $fixture['title'],
                        'message' => $fixture['message'],
                        'status' => $fixture['status'],
                        'url' => $fixture['url'],
                    ],
                    'read_at' => $fixture['read'] ? now()->subMinutes(20) : null,
                ],
            );
        }

        $appointment = Appointment::query()->where('idempotency_key', '10000000-0000-4000-8000-000000000002')->first();
        $record = MedicalRecord::query()->whereNotNull('odontogram')->first();
        $subscription = Subscription::query()->where('clinic_id', $clinic->id)->first();
        $payment = Payment::query()->where('invoice_number', 'DEMO-INV-PAID')->first();

        $this->seedAudit($users->get('admin@clinicos.test'), $clinic, 'auth.login', User::class, $users->get('admin@clinicos.test')?->id, ['guard' => 'web']);
        $this->seedAudit($users->get('receptionist@clinicos.test'), $clinic, 'appointment.created', Appointment::class, $appointment?->id, ['source' => 'RECEPTIONIST']);
        $this->seedAudit($users->get('dentist@clinicos.test'), $clinic, 'medical_record.finalized', MedicalRecord::class, $record?->id, ['record_type' => 'dental']);
        $this->seedAudit($users->get('platform@clinicos.test'), $clinic, 'subscription.granted', Subscription::class, $subscription?->id, ['plan' => 'team']);
        $this->seedAudit($users->get('platform@clinicos.test'), $clinic, 'payment.notification.processed', Payment::class, $payment?->id, ['provider' => 'doku', 'result' => 'processed']);
    }

    /**
     * @return list<array{id: string, email: string, title: string, message: string, status: string, url: string, read: bool}>
     */
    private function notificationFixtures(): array
    {
        return [
            ['id' => '60000000-0000-4000-8000-000000000001', 'email' => 'admin@clinicos.test', 'title' => 'Ringkasan operasional tersedia', 'message' => 'Data kunjungan dan antrean demo hari ini siap ditinjau.', 'status' => 'info', 'url' => '/admin/dashboard', 'read' => false],
            ['id' => '60000000-0000-4000-8000-000000000002', 'email' => 'receptionist@clinicos.test', 'title' => 'Pasien sudah check-in', 'message' => 'Antrean KS-102 sekarang berstatus Menunggu.', 'status' => 'WAITING', 'url' => '/receptionist/queues', 'read' => false],
            ['id' => '60000000-0000-4000-8000-000000000003', 'email' => 'doctor@clinicos.test', 'title' => 'Pemeriksaan aktif', 'message' => 'Antrean KS-104 siap dilanjutkan pada halaman pemeriksaan.', 'status' => 'IN_PROGRESS', 'url' => '/doctor/queues', 'read' => false],
            ['id' => '60000000-0000-4000-8000-000000000004', 'email' => 'dentist@clinicos.test', 'title' => 'Antrean dokter gigi dipanggil', 'message' => 'Antrean KS-103 telah dipanggil.', 'status' => 'CALLED', 'url' => '/doctor/queues', 'read' => true],
            ['id' => '60000000-0000-4000-8000-000000000005', 'email' => 'patient@clinicos.test', 'title' => 'Booking berhasil dibuat', 'message' => 'Booking demo Anda telah dikonfirmasi oleh Klinik Sehat Sentosa.', 'status' => 'BOOKED', 'url' => '/patient/dashboard', 'read' => false],
            ['id' => '60000000-0000-4000-8000-000000000006', 'email' => 'platform@clinicos.test', 'title' => 'Pembayaran demo diproses', 'message' => 'Invoice DEMO-INV-PAID berhasil diproses sebagai data demonstrasi.', 'status' => 'paid', 'url' => '/platform/payments', 'read' => true],
        ];
    }

    /** @param array<string, mixed> $metadata */
    private function seedAudit(?User $actor, Clinic $clinic, string $action, string $entityType, ?int $entityId, array $metadata): void
    {
        if ($actor === null || $entityId === null) {
            return;
        }

        AuditLog::query()->updateOrCreate(
            [
                'clinic_id' => $clinic->id,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ],
            [
                'user_id' => $actor->id,
                'actor_name' => $actor->name,
                'ip_address' => '127.0.0.1',
                'metadata' => $metadata + ['fixture' => 'demo'],
            ],
        );
    }
}

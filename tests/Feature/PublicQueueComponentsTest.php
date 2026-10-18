<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class PublicQueueComponentsTest extends TestCase
{
    public function test_queue_number_display_renders_only_public_props(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-queue-number-display
                display-number="A-012"
                poli-label="Poli Umum"
                doctor-label="dr. Budi"
                session-label="Sesi 08:00–12:00"
                status="CALLED"
            />
        BLADE);

        $this->assertStringContainsString('A-012', $html);
        $this->assertStringContainsString('Poli Umum', $html);
        $this->assertStringContainsString('Dipanggil', $html);
        $this->assertStringNotContainsString('Pasien', $html);
        $this->assertStringNotContainsString('rekam medis', $html);
    }

    public function test_public_queue_screen_renders_mock_queue_data_responsively(): void
    {
        $html = Blade::render(
            <<<'BLADE'
                <x-public-queue-screen
                    clinic-name="Klinik Sehat"
                    date-label="18 Oktober 2026"
                    :queues="$queues"
                />
            BLADE,
            ['queues' => [[
                'display_number' => 'B-004',
                'poli_label' => 'Poli Anak',
                'doctor_label' => null,
                'session_label' => 'Sesi 13:00–16:00',
                'status' => 'WAITING',
            ]]],
        );

        $this->assertStringContainsString('Klinik Sehat', $html);
        $this->assertStringContainsString('B-004', $html);
        $this->assertStringContainsString('Poli Anak', $html);
        $this->assertStringContainsString('overflow-x-hidden', $html);
        $this->assertStringContainsString('sm:grid-cols-2', $html);
        $this->assertStringContainsString('2xl:grid-cols-4', $html);
    }

    public function test_public_queue_screen_has_a_no_data_state(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-public-queue-screen
                clinic-name="Klinik Sehat"
                date-label="18 Oktober 2026"
                :queues="[]"
            />
        BLADE);

        $this->assertStringContainsString('Belum ada antrean aktif', $html);
        $this->assertStringContainsString('Nomor antrean aktif akan muncul setelah pasien check-in.', $html);
    }

    public function test_queue_number_display_renders_eta_confidence_and_stale_states(): void
    {
        foreach ([
            'high' => 'Keyakinan tinggi',
            'medium' => 'Keyakinan sedang',
            'low' => 'Keyakinan rendah',
        ] as $confidence => $label) {
            $html = Blade::render(
                <<<'BLADE'
                    <x-queue-number-display
                        display-number="A-012"
                        poli-label="Poli Umum"
                        status="WAITING"
                        :eta-min-minutes="10"
                        :eta-max-minutes="20"
                        :confidence="$confidence"
                        :is-stale="$isStale"
                    />
                BLADE,
                ['confidence' => $confidence, 'isStale' => $confidence === 'low'],
            );

            $this->assertStringContainsString('Perkiraan 10–20 menit', $html);
            $this->assertStringContainsString($label, $html);
        }

        $this->assertStringContainsString('Perkiraan dapat berubah.', $html);
        $this->assertStringContainsString('Data estimasi sudah lama. Menampilkan perkiraan terakhir.', $html);
    }

    public function test_queue_number_display_does_not_present_invalid_eta_as_a_promise(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-queue-number-display
                display-number="A-012"
                poli-label="Poli Umum"
                status="WAITING"
                :eta-min-minutes="20"
                :eta-max-minutes="10"
            />
        BLADE);

        $this->assertStringContainsString('Estimasi belum tersedia.', $html);
        $this->assertStringNotContainsString('Perkiraan 20–10 menit', $html);
    }

    public function test_public_queue_screen_renders_every_connection_state_with_text(): void
    {
        foreach ([
            'connected' => 'Terhubung · diperbarui otomatis',
            'refetching' => 'Memperbarui data…',
            'reconnecting' => 'Menghubungkan kembali…',
            'disconnected' => 'Koneksi terputus · menampilkan data terakhir',
        ] as $state => $label) {
            $html = Blade::render(
                <<<'BLADE'
                    <x-public-queue-screen
                        clinic-name="Klinik Sehat"
                        date-label="18 Oktober 2026"
                        :queues="[]"
                        :connection-state="$state"
                    />
                BLADE,
                ['state' => $state],
            );

            $this->assertStringContainsString($label, $html);
            $this->assertStringContainsString('data-realtime-connection-state="'.$state.'"', $html);
            $this->assertStringContainsString('aria-live="polite"', $html);
        }
    }
}

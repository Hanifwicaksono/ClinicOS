<?php

namespace Tests\Feature;

use App\VisitStatus;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class VisitSummaryComponentTest extends TestCase
{
    public function test_it_renders_visit_date_doctor_status_and_responsive_contract_from_props(): void
    {
        $html = Blade::render(
            <<<'BLADE'
                <x-visit-summary
                    title="Kunjungan hari ini"
                    description="Data fixture presentasional."
                    heading-id="today-visit-summary"
                    :visits="$visits"
                />
            BLADE,
            ['visits' => [
                [
                    'date' => '2 November 2026, 09:15',
                    'datetime' => '2026-11-02T09:15:00+07:00',
                    'doctor' => 'dr. Maya Putri, Sp.PD',
                    'service' => 'Konsultasi penyakit dalam',
                    'status' => VisitStatus::Completed,
                ],
                [
                    'date' => '2 November 2026, 10:40',
                    'doctor' => 'drg. Raka Adinata',
                    'status' => 'IN_PROGRESS',
                ],
            ]],
        );

        $this->assertStringContainsString('data-visit-summary', $html);
        $this->assertStringContainsString('aria-labelledby="today-visit-summary"', $html);
        $this->assertStringContainsString('id="today-visit-summary"', $html);
        $this->assertStringContainsString('Kunjungan hari ini', $html);
        $this->assertStringContainsString('2 November 2026, 09:15', $html);
        $this->assertStringContainsString('datetime="2026-11-02T09:15:00+07:00"', $html);
        $this->assertStringContainsString('dr. Maya Putri, Sp.PD', $html);
        $this->assertStringContainsString('Konsultasi penyakit dalam', $html);
        $this->assertStringContainsString('Selesai', $html);
        $this->assertStringContainsString('Sedang diperiksa', $html);
        $this->assertStringContainsString('2 kunjungan', $html);
        $this->assertStringContainsString('min-w-0', $html);
        $this->assertStringContainsString('overflow-hidden', $html);
        $this->assertStringContainsString('sm:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)_auto]', $html);
        $this->assertStringContainsString('break-words', $html);
        $this->assertStringNotContainsString('fetch(', $html);
        $this->assertStringNotContainsString('data-realtime', $html);
    }

    public function test_it_renders_custom_empty_state_from_props(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-visit-summary
                :visits="[]"
                empty-title="Belum ada kunjungan hari ini"
                empty-message="Kunjungan akan tampil setelah pemeriksaan dimulai."
            />
        BLADE);

        $this->assertStringContainsString('data-visit-summary-empty', $html);
        $this->assertStringContainsString('Belum ada kunjungan hari ini', $html);
        $this->assertStringContainsString('Kunjungan akan tampil setelah pemeriksaan dimulai.', $html);
        $this->assertStringNotContainsString('data-visit-summary-item', $html);
    }

    public function test_local_preview_renders_populated_and_empty_mock_states(): void
    {
        $this->get(route('previews.visit-summary'))
            ->assertOk()
            ->assertSeeText('Component preview - mock data')
            ->assertSeeText('dr. Maya Putri, Sp.PD')
            ->assertSeeText('Sedang diperiksa')
            ->assertSeeText('Belum ada kunjungan');
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class DentalChartComponentTest extends TestCase
{
    public function test_dental_chart_renders_state_legend_keyboard_controls_and_callbacks_without_persistence(): void
    {
        $html = Blade::render(
            '<x-dental-chart :teeth="$teeth" :conditions="$conditions" selected-tooth="11" />',
            [
                'teeth' => [
                    ['id' => '11', 'label' => 'Gigi 11', 'quadrant' => 'upper-left', 'condition' => 'caries'],
                    ['id' => '12', 'label' => 'Gigi 12', 'quadrant' => 'upper-left', 'condition' => 'healthy', 'disabled' => true],
                ],
                'conditions' => [
                    ['value' => 'healthy', 'label' => 'Sehat'],
                    ['value' => 'caries', 'label' => 'Karies'],
                ],
            ],
        );

        $this->assertStringContainsString('data-dental-chart', $html);
        $this->assertStringContainsString('data-initial-selected-tooth="11"', $html);
        $this->assertStringContainsString('data-tooth-id="11"', $html);
        $this->assertStringContainsString('data-tooth-id="12"', $html);
        $this->assertStringContainsString('disabled', $html);
        $this->assertStringContainsString('Karies', $html);
        $this->assertStringContainsString('Sehat', $html);
        $this->assertStringContainsString('x-bind:aria-pressed', $html);
        $this->assertStringContainsString('focus-visible:ring-2', $html);
        $this->assertStringContainsString("\$dispatch('dental-tooth-selected'", $html);
        $this->assertStringContainsString("\$dispatch('dental-condition-changed'", $html);
        $this->assertStringNotContainsString('<form', $html);
        $this->assertStringNotContainsString('fetch(', $html);
        $this->assertStringNotContainsString('name=', $html);
    }

    public function test_dental_chart_supports_read_only_state(): void
    {
        $html = Blade::render(
            '<x-dental-chart :teeth="$teeth" :conditions="$conditions" read-only />',
            [
                'teeth' => [['id' => '21', 'label' => 'Gigi 21', 'quadrant' => 'upper-left']],
                'conditions' => [['value' => 'unknown', 'label' => 'Belum dicatat']],
            ],
        );

        $this->assertStringContainsString('data-read-only="true"', $html);
        $this->assertStringContainsString('Read-only', $html);
        $this->assertStringContainsString('disabled', $html);
    }

    public function test_critical_allergy_banner_only_renders_supplied_data(): void
    {
        $emptyHtml = Blade::render('<x-critical-allergy-banner :allergies="[]" />');
        $html = Blade::render(
            '<x-critical-allergy-banner :allergies="$allergies" />',
            ['allergies' => [[
                'substance' => 'Penisilin',
                'reaction' => 'Anafilaksis',
                'severity' => 'Kritis',
            ]]],
        );

        $this->assertSame('', trim($emptyHtml));
        $this->assertStringContainsString('data-critical-allergy-alert', $html);
        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringContainsString('tabindex="0"', $html);
        $this->assertStringContainsString('Alergi kritis', $html);
        $this->assertStringContainsString('Penisilin', $html);
        $this->assertStringContainsString('Anafilaksis', $html);
        $this->assertStringContainsString('Keparahan: Kritis', $html);
    }
}

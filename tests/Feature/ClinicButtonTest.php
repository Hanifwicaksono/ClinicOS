<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ClinicButtonTest extends TestCase
{
    public function test_button_submits_forms_by_default(): void
    {
        $html = Blade::render('<x-clinic-button>Simpan</x-clinic-button>');

        $this->assertStringContainsString('type="submit"', $html);
    }

    public function test_button_type_can_be_overridden(): void
    {
        $html = Blade::render('<x-clinic-button type="button">Buka</x-clinic-button>');

        $this->assertStringContainsString('type="button"', $html);
    }
}

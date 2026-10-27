<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_contains_accessible_mobile_navigation(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('aria-controls="co-mobile-menu"', false)
            ->assertSee('id="co-mobile-menu"', false)
            ->assertSee('Buka menu');
    }

    public function test_local_proxy_https_header_is_trusted(): void
    {
        $host = parse_url(config('app.url'), PHP_URL_HOST);
        $response = $this
            ->withServerVariables([
                'REMOTE_ADDR' => '127.0.0.1',
                'HTTP_HOST' => $host,
                'HTTP_X_FORWARDED_HOST' => $host,
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ])
            ->get('/');

        $response
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}

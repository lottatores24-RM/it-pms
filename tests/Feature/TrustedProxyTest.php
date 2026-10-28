<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Behind a local tunnel (cloudflared) links must use the public https address,
 * but forwarded headers from any other client are ignored.
 */
class TrustedProxyTest extends TestCase
{
    private const FORWARDED = [
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'demo.trycloudflare.com',
        'X-Forwarded-Port' => '443',
    ];

    public function test_links_use_the_public_https_address_behind_a_local_tunnel(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get('/login', self::FORWARDED)
            ->assertOk()
            ->assertSee('action="https://demo.trycloudflare.com/login"', false);
    }

    public function test_forwarded_headers_from_other_clients_are_ignored(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->get('/login', self::FORWARDED)
            ->assertOk()
            ->assertDontSee('demo.trycloudflare.com', false);
    }
}

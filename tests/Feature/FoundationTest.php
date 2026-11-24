<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\EnvCheck;
use App\Support\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_and_request_id(): void
    {
        User::create(['name' => 'A', 'email' => 'a@x.test', 'password' => 'Secret12345', 'role' => 'admin']);
        $r = $this->get('/login')->assertOk();
        $csp = $r->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[^']+'/", $csp);
        $this->assertSame('nosniff', $r->headers->get('X-Content-Type-Options'));
        $this->assertSame('DENY', $r->headers->get('X-Frame-Options'));
        $this->assertNotEmpty($r->headers->get('X-Request-Id'));
        $this->assertNull($r->headers->get('Strict-Transport-Security'), 'HSTS only on HTTPS');
        // A well-formed incoming ID is kept, a malicious one is replaced.
        $this->assertSame('abcd1234-ok', $this->get('/login', ['X-Request-Id' => 'abcd1234-ok'])->headers->get('X-Request-Id'));
        $this->assertNotSame('<script>', $this->get('/login', ['X-Request-Id' => '<script>'])->headers->get('X-Request-Id'));
    }

    public function test_hsts_on_https(): void
    {
        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security');
    }

    public function test_health_endpoint(): void
    {
        $this->getJson('/health')->assertOk()->assertJsonPath('status', 'ok')->assertJsonPath('checks.database', true)->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_custom_error_pages_without_internals(): void
    {
        config(['app.debug' => false]);
        $this->get('/no-such-page')->assertNotFound()->assertSee('Page not found')->assertSee('Reference:');
        $this->getJson('/no-such-page')->assertNotFound()->assertJsonStructure(['message', 'request_id'])->assertJsonMissingPath('trace');
    }

    public function test_inline_scripts_carry_the_csp_nonce(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin'])->fresh());
        $r = $this->get('/settings')->assertOk();
        preg_match("/'nonce-([^']+)'/", $r->headers->get('Content-Security-Policy'), $m);
        $html = $r->getContent();
        preg_match_all('/<script(?![^>]*\bsrc=)[^>]*>/', $html, $tags);
        foreach ($tags[0] as $tag) {
            $this->assertStringContainsString('nonce="'.$m[1].'"', $tag, 'inline script without nonce: '.$tag);
        }
        $this->assertDoesNotMatchRegularExpression('/\son(click|change|submit|input)="/', $html, 'inline event handlers are blocked by the CSP');
    }

    public function test_write_requests_are_rate_limited(): void
    {
        $u = User::factory()->create(['role' => 'admin'])->fresh();
        Setting::put('setup_completed_at', now()->toDateTimeString());
        $this->actingAs($u);
        for ($i = 0; $i < 120; $i++) {
            $this->post('/announcements', []);
        }
        $this->post('/announcements', [])->assertStatus(429);
        $this->get('/dashboard')->assertOk(); // reads are not throttled
    }

    public function test_school_mode_service(): void
    {
        $this->assertSame(School::HOSTED, School::mode());
        $this->assertTrue(School::allows('student_accounts'));
        Setting::put('deployment_mode', School::LAN);
        $this->assertTrue(School::isLan());
        $this->assertFalse(School::allows('student_accounts'));
        $this->assertFalse(School::allows('email_notifications'));
        $this->assertFalse(School::allows('unknown_feature'));
        Setting::put('deployment_mode', 'weird');
        $this->assertSame(School::HOSTED, School::mode());
        Setting::put('deployment_mode', '');
        config(['smartschool.mode' => 'lan']);
        $this->assertTrue(School::isLan(), 'falls back to the mode chosen in the installer');
    }

    public function test_env_check(): void
    {
        $ok = ['app.key' => 'base64:x', 'database.default' => 'mysql', 'app.debug' => false, 'app.url' => 'https://school.example'];
        $this->assertSame([], EnvCheck::problems($ok, 'production'));
        $this->assertCount(2, EnvCheck::problems(['app.debug' => true, 'app.url' => 'http://x'] + $ok, 'production'));
        $this->assertCount(1, EnvCheck::problems(['app.key' => ''] + $ok, 'local'));
        $this->assertSame([], EnvCheck::problems(['app.url' => 'http://192.168.1.10:8000', 'smartschool.mode' => 'lan'] + $ok, 'production'));
    }
}

<?php

namespace Tests\Feature;

use App\Mail\SignInCodeMail;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\SignInCode;
use App\Support\School;
use App\Support\SchoolMail;
use App\Support\TwoStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MailAndTwoStepTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Setting::put('setup_completed_at', now()->toDateTimeString());

        return User::factory()->create(['role' => 'admin', 'password' => 'secret-pass-123'])->fresh();
    }

    private function smtp(): void
    {
        SchoolMail::save(['mail_host' => 'smtp.example.com', 'mail_port' => 587, 'mail_encryption' => 'tls', 'mail_username' => 'u', 'mail_password' => 'p@ss', 'mail_from_address' => 'noreply@example.com', 'mail_from_name' => 'School']);
    }

    public function test_smtp_password_is_encrypted_and_applied(): void
    {
        $this->smtp();
        $this->assertNotSame('p@ss', Setting::get('mail_password'));
        $this->assertSame('p@ss', SchoolMail::password());
        SchoolMail::apply();
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.example.com', config('mail.mailers.smtp.host'));
        $this->assertSame('noreply@example.com', config('mail.from.address'));
    }

    public function test_smtp_not_applied_on_lan(): void
    {
        Setting::put('deployment_mode', School::LAN);
        $this->smtp();
        config(['mail.default' => 'array']);
        SchoolMail::apply();
        $this->assertSame('array', config('mail.default'));
    }

    public function test_settings_page_shows_smtp_and_security_and_internet_warning(): void
    {
        $this->actingAs($this->admin())->get('/settings')->assertOk()
            ->assertSee('Email (SMTP)')->assertSee('Sign-in security')->assertSee('everyone needs internet', false);
    }

    public function test_two_step_cannot_be_enabled_without_email(): void
    {
        $this->assertFalse(TwoStep::available());
        $this->actingAs($this->admin());
        Setting::put('two_step', 'staff');
        $this->assertFalse(TwoStep::requiredFor(auth()->user())); // no SMTP → never locks anyone out
    }

    public function test_two_step_sign_in_flow(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $this->smtp();
        Setting::put('two_step', 'staff');

        $this->post('/login', ['email' => $admin->email, 'password' => 'secret-pass-123'])->assertRedirect(route('login.verify'));
        $this->assertGuest();
        $code = null;
        Notification::assertSentTo($admin, SignInCode::class, function ($n) use (&$code) {
            $code = $n->code;

            return true;
        });

        $this->get('/login/code')->assertOk()->assertSee('Check your email');
        $this->post('/login/code', ['code' => $code === '000000' ? '111111' : '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->post('/login/code', ['code' => $code])->assertRedirect();
        $this->assertAuthenticatedAs($admin);
    }

    public function test_parents_skip_two_step_when_staff_only(): void
    {
        Notification::fake();
        $this->smtp();
        Setting::put('setup_completed_at', now()->toDateTimeString());
        Setting::put('two_step', 'staff');
        $parent = User::factory()->create(['role' => 'parent', 'password' => 'secret-pass-123']);
        $this->post('/login', ['email' => $parent->email, 'password' => 'secret-pass-123']);
        $this->assertAuthenticatedAs($parent);
        Notification::assertNothingSent();
    }

    public function test_sign_in_code_email_is_branded(): void
    {
        Setting::put('school_name', 'Mabini High');
        $html = (new SignInCodeMail('123456', 'Ana'))->render();
        $this->assertStringContainsString('123456', $html);
        $this->assertStringContainsString('Mabini High', $html);
        $this->assertStringContainsString('Hi Ana', $html);
        $this->assertStringContainsString('#4f46e5', $html);
    }

    public function test_setup_wizard_asks_for_email_only_when_online(): void
    {
        $this->actingAs($this->admin());
        $this->get('/setup-wizard/email')->assertOk()->assertSee('SMTP server')->assertSee('Two-step sign-in');
        $this->post('/setup-wizard/email', ['mail_host' => 'smtp.example.com', 'mail_port' => 587, 'mail_encryption' => 'tls', 'mail_from_address' => 'a@example.com', 'two_step' => 'staff'])->assertRedirect();
        $this->assertSame('staff', Setting::get('two_step'));
        Setting::put('deployment_mode', School::LAN);
        $this->get('/setup-wizard/email')->assertNotFound();
    }
}

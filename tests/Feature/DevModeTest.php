<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DevModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        config(['smartschool.dev' => true, 'smartschool.dev_password_hash' => Hash::make('devpw')]);
    }

    public function test_superadmin_can_view_as_teacher_and_return(): void
    {
        $admin = User::where('role', 'admin')->first();
        $teacher = User::where('role', 'teacher')->first();
        $this->actingAs($admin)->post('/dev/unlock', ['password' => 'devpw'])->assertSessionHas('ok');
        $this->post('/dev/login', ['user_id' => $teacher->id])->assertRedirect('/');
        $this->assertAuthenticatedAs($teacher);
        $this->assertTrue(AuditLog::where('action', 'impersonate')->where('user_id', $admin->id)->exists());

        // reloads keep the bar and the switcher
        $this->get('/dashboard')->assertOk()->assertSee('Return to Superadmin')->assertSee('Developer mode');
        $this->get('/dashboard')->assertSee('Viewing as');
        // can switch again while viewing as the teacher
        $this->post('/dev/login', ['user_id' => User::where('role', 'cashier')->first()->id])->assertRedirect('/');

        $this->post('/dev/back')->assertRedirect('/');
        $this->assertAuthenticatedAs($admin);
        $this->assertTrue(AuditLog::where('action', 'impersonate_end')->exists());
        $this->get('/dashboard')->assertDontSee('Return to Superadmin');
    }

    public function test_non_superadmin_cannot_start_impersonation(): void
    {
        $teacher = User::where('role', 'teacher')->first();
        $this->actingAs($teacher)->get('/dashboard')->assertDontSee('Developer mode');
        $this->post('/dev/unlock', ['password' => 'devpw'])->assertNotFound();
        $this->withSession(['dev_unlocked' => true])->post('/dev/login', ['user_id' => User::where('role', 'admin')->first()->id])->assertNotFound();
        $this->post('/dev/back')->assertNotFound();
        $this->assertAuthenticatedAs($teacher);
    }

    public function test_forged_real_user_id_of_non_admin_is_rejected(): void
    {
        $teacher = User::where('role', 'teacher')->first();
        $this->actingAs($teacher)->withSession(['dev_unlocked' => true, 'dev_real_user_id' => $teacher->id])
            ->post('/dev/login', ['user_id' => User::where('role', 'admin')->first()->id])->assertNotFound();
    }

    public function test_logout_ends_impersonation(): void
    {
        $admin = User::where('role', 'admin')->first();
        $this->actingAs($admin)->post('/dev/unlock', ['password' => 'devpw']);
        $this->post('/dev/login', ['user_id' => User::where('role', 'teacher')->first()->id]);
        $this->post('/logout');
        $this->assertGuest();
        $this->actingAs($admin)->get('/dashboard')->assertDontSee('Return to Superadmin')->assertSee('Developer mode');
    }
}

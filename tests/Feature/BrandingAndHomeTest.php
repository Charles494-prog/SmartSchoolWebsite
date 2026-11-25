<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingAndHomeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Setting::put('setup_completed_at', now()->toDateTimeString());

        return User::factory()->create(['role' => 'admin'])->fresh();
    }

    public function test_setup_wizard_saves_logo_and_it_becomes_the_favicon(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin());

        $this->get('/setup-wizard/school')->assertOk()->assertSee('enctype="multipart/form-data"', false)->assertSee('name="logo"', false);

        $this->post('/setup-wizard/school', ['school_name' => 'Mabini High', 'logo' => UploadedFile::fake()->image('logo.png', 256, 256)])->assertRedirect();
        $path = Setting::get('logo_path');
        $this->assertNotEmpty($path);
        Storage::disk('local')->assertExists($path);

        $url = Setting::logoUrl();
        $this->get('/dashboard')->assertOk()->assertSee('<link rel="icon" href="'.e($url).'">', false);
        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
    }

    public function test_replacing_the_logo_removes_the_old_file(): void
    {
        Storage::fake('local');
        $this->admin();
        Setting::storeLogo(UploadedFile::fake()->image('a.png'));
        $old = Setting::get('logo_path');
        Setting::storeLogo(UploadedFile::fake()->image('b.png'));
        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists(Setting::get('logo_path'));
    }

    public function test_logo_upload_rejects_non_images(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin());
        $this->post('/setup-wizard/school', ['school_name' => 'X', 'logo' => UploadedFile::fake()->create('evil.svg', 5, 'image/svg+xml')])->assertSessionHasErrors('logo');
        $this->assertEmpty(Setting::get('logo_path'));
    }

    public function test_default_favicon_without_logo(): void
    {
        $this->actingAs($this->admin())->get('/dashboard')->assertOk()->assertSee('favicon.ico', false);
    }

    public function test_home_lists_grouped_features_with_filter_and_sidebar_has_no_search(): void
    {
        $html = $this->actingAs($this->admin())->get('/dashboard')->assertOk()
            ->assertSee('What would you like to do?')
            ->assertSee('Type to filter', false)
            ->assertSee('Subjects')
            ->assertDontSee('Search everything')
            ->getContent();

        foreach (['Students', 'Teaching', 'Attendance', 'Payments', 'Communication', 'School setup'] as $group) {
            $this->assertStringContainsString('>'.$group.'</h3>', $html);
        }
        $this->assertStringContainsString("dispatch('palette')", $html); // top-bar search stays
    }

    public function test_settings_read_after_write_is_consistent(): void
    {
        Setting::put('school_name', 'Old');
        $this->assertSame('Old', Setting::get('school_name'));
        Setting::put('school_name', 'New');
        $this->assertSame('New', Setting::get('school_name'));
        Setting::flush();
        $this->assertSame('New', Setting::get('school_name'));
    }

    public function test_light_mode_is_the_default(): void
    {
        $html = $this->actingAs($this->admin())->get('/dashboard')->assertOk()->getContent();
        $this->assertStringContainsString("if (localStorage.theme === 'dark') document.documentElement.classList.add('dark')", $html);
        $this->assertStringNotContainsString('prefers-color-scheme', $html);
    }

    public function test_announcements_feed_has_composer_and_posts(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/announcements')->assertOk()->assertSee('on your mind', false)->assertSee('No announcements yet');
        $this->post('/announcements', ['title' => 'Foundation Day', 'body' => 'No classes on Friday.', 'audience' => 'all'])->assertRedirect();
        $this->get('/announcements')->assertOk()->assertSee('Foundation Day')->assertSee('No classes on Friday.')->assertSee($admin->name)->assertSee('Delete post');
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\GeneralNotice;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    public function test_list_groups_similar_requests_and_does_not_auto_mark_read(): void
    {
        $reg = User::where('role', 'registrar')->first();
        $reg->notifications()->delete();
        foreach (['TOR', 'Good moral', 'Diploma', 'Enrollment cert'] as $doc) {
            $reg->notify(new GeneralNotice('New request: '.$doc, 'Mendoza, Ana B.', '/requests'));
        }
        $this->actingAs($reg)->get('/notifications')->assertOk()->assertSee('Mendoza, Ana B. requested 4 documents')->assertSee('Today')->assertSee('Mark all as seen');
        $this->assertSame(4, $reg->unreadNotifications()->count());
        $this->get('/notifications?filter=payment')->assertOk()->assertSee('Nothing here');
        $this->get('/notifications?filter=request')->assertOk()->assertSee('requested 4 documents');
    }

    public function test_actions_are_per_user(): void
    {
        $a = User::where('role', 'registrar')->first();
        $b = User::where('role', 'admin')->first();
        Notification::send([$a, $b], new GeneralNotice('Shared', 'Same notice', '/dashboard'));
        $idA = $a->notifications()->first()->id;
        $this->actingAs($a)->postJson('/notifications', ['action' => 'delete', 'ids' => [$idA]])->assertOk();
        $this->assertSame(0, $a->notifications()->where('data->title', 'Shared')->count());
        $this->assertSame(1, $b->notifications()->where('data->title', 'Shared')->count());
        // cannot touch someone else's
        $idB = $b->notifications()->where('data->title', 'Shared')->first()->id;
        $this->postJson('/notifications', ['action' => 'delete', 'ids' => [$idB]])->assertOk()->assertJson(['changed' => 0]);
    }

    public function test_mark_all_read_unread_clear_read_and_open(): void
    {
        $s = User::where('email', 'student@school.test')->first();
        $s->notifications()->delete();
        $s->notify(new GeneralNotice('One', 'x', '/portal/grades'));
        $s->notify(new GeneralNotice('Two', 'y', 'https://evil.example/phish'));
        $this->actingAs($s)->postJson('/notifications', ['action' => 'read_all'])->assertJson(['unread' => 0]);
        $id = $s->notifications()->where('data->title', 'One')->first()->id;
        $this->postJson('/notifications', ['action' => 'unread', 'ids' => [$id]])->assertJson(['unread' => 1]);
        $this->get("/notifications/$id/open")->assertRedirect('/portal/grades');
        $this->assertSame(0, $s->unreadNotifications()->count());
        $evil = $s->notifications()->where('data->title', 'Two')->first()->id;
        $this->get("/notifications/$evil/open")->assertRedirect('/');
        $this->post('/notifications', ['action' => 'clear_read'])->assertSessionHas('ok');
        $this->assertSame(0, $s->notifications()->count());
    }
}

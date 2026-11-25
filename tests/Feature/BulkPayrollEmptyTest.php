<?php

namespace Tests\Feature;

use App\Models\ClassSubject;
use App\Models\Room;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use App\Services\Payroll;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkPayrollEmptyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->actingAs(User::where('email', 'admin@school.test')->firstOrFail());
    }

    public function test_list_has_select_and_icon_actions(): void
    {
        $this->get('/manage/subjects')->assertOk()->assertSee('Delete selected')->assertSee('aria-label="Edit"', false)->assertDontSee('>Edit</a>', false);
    }

    public function test_bulk_delete_deletes_selected_and_reports_skips(): void
    {
        $a = Room::create(['name' => 'Bulk A', 'capacity' => 10]);
        $b = Room::create(['name' => 'Bulk B', 'capacity' => 10]);
        $this->post('/manage/rooms/bulk-delete', ['ids' => [$a->id, $b->id]])->assertRedirect()->assertSessionHas('ok', fn ($m) => str_starts_with($m, '2 rooms deleted'));
        $this->assertDatabaseMissing('rooms', ['id' => $a->id]);
        $this->post('/manage/rooms/bulk-delete', [])->assertSessionHasErrors('ids');

        $me = auth()->user();
        $this->post('/manage/users/bulk-delete', ['ids' => [$me->id]])->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $me->id]);
    }

    public function test_bulk_delete_respects_roles(): void
    {
        $this->actingAs(User::where('role', 'teacher')->firstOrFail());
        $this->post('/manage/rooms/bulk-delete', ['ids' => [1]])->assertForbidden();
    }

    public function test_form_groups_and_weight_total(): void
    {
        $this->get('/manage/subjects/create')->assertOk()->assertSee('How the grade is computed')->assertSee('Total of all weights');
    }

    public function test_payroll_rates_are_configurable(): void
    {
        $this->assertSame(200.0, Payroll::pagibig(30000));
        $this->get('/payroll/rates')->assertOk()->assertSee('Pag-IBIG');
        $c = collect(Payroll::contributions())->map(fn ($x) => ['rate' => $x['rate'], 'min' => $x['min'], 'max' => $x['max']])->all();
        $c['pagibig']['rate'] = 1;
        $this->put('/payroll/rates', ['c' => $c, 'brackets' => [['over' => 25000, 'base' => 0, 'rate' => 10]]])->assertSessionHas('ok');
        $this->assertSame(100.0, Payroll::pagibig(30000));
        $this->assertSame(0.0, Payroll::withholding(25000));
        $this->assertSame(100.0, Payroll::withholding(26000));

        $c['sss']['max'] = 1;
        $this->put('/payroll/rates', ['c' => $c])->assertSessionHasErrors('c.sss.max');

        $this->post('/payroll/rates/reset')->assertSessionHas('ok');
        $this->assertSame(200.0, Payroll::pagibig(30000));
    }

    public function test_empty_states_show_button_and_steps(): void
    {
        $section = Section::first();
        ClassSubject::where('section_id', $section->id)->delete();
        $this->get("/sections/{$section->id}")->assertOk()->assertSee('Show me how');
        Subject::query()->delete();
        $this->get('/manage/subjects')->assertOk()->assertSee('No subjects yet')->assertSee('Show me how');
    }
}

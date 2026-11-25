<?php

namespace Tests\Feature;

use App\Models\ClassSubject;
use App\Models\GradeSubmission;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeApprovalsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    private function submitted(int $n = 2)
    {
        return ClassSubject::take($n)->get()->map(fn ($c) => GradeSubmission::updateOrCreate(['class_subject_id' => $c->id, 'term' => 2], ['status' => 'submitted', 'submitted_by' => $c->teacher_id]));
    }

    public function test_queue_review_return_requires_comment_and_bulk_approve(): void
    {
        [$a, $b] = $this->submitted()->all();
        $this->actingAs(User::where('role', 'registrar')->first());
        $this->get('/grade-approvals?term=2')->assertOk()->assertSee('Needs approval')->assertSee('Review')->assertSee('Awaiting approval');
        $this->get("/grade-approvals/{$a->id}")->assertOk()->assertSee('Class average')->assertSee('Missing scores');
        $this->post("/grade-submissions/{$a->id}", ['action' => 'return'])->assertSessionHasErrors('remarks');
        $this->post("/grade-submissions/{$a->id}", ['action' => 'return', 'remarks' => 'Fix exam', 'back' => 'queue'])->assertRedirect(route('approvals.index', ['term' => 2]));
        $this->assertSame('returned', $a->fresh()->status);
        $this->get('/grade-approvals?term=2&tab=returned')->assertSee('Fix exam');
        $this->post('/grade-approvals/bulk', ['ids' => [$a->id, $b->id]])->assertSessionHas('ok', 'Approved 1 class.');
        $this->assertSame('approved', $b->fresh()->status);
        $this->assertSame('returned', $a->fresh()->status);
    }

    public function test_teacher_cannot_use_queue(): void
    {
        $this->actingAs(User::where('role', 'teacher')->first())->get('/grade-approvals')->assertForbidden();
    }
}

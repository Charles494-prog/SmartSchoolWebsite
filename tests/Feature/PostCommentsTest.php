<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\ClassSubject;
use App\Models\PostComment;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostCommentsTest extends TestCase
{
    use RefreshDatabase;

    private ClassSubject $class;

    private User $teacher;

    private User $student;

    private Assignment $post;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        Setting::put('deployment_mode', 'hosted');
        $this->student = User::where('role', 'student')->get()->first(fn ($u) => $u->student?->currentEnrollment()?->section_id
            && ClassSubject::where('section_id', $u->student->currentEnrollment()->section_id)->whereNotNull('teacher_id')->exists());
        $this->class = ClassSubject::where('section_id', $this->student->student->currentEnrollment()->section_id)->whereNotNull('teacher_id')->first();
        $this->teacher = User::find($this->class->teacher_id);
        $this->post = Assignment::create(['class_subject_id' => $this->class->id, 'type' => 'announcement', 'title' => 'Field trip', 'instructions' => 'Bring lunch']);
    }

    public function test_students_comment_teacher_moderates(): void
    {
        $this->actingAs($this->student)->post(route('posts.comments.store', $this->post), ['body' => 'Can we bring snacks?'])->assertSessionHas('ok');
        $comment = PostComment::latest('id')->first();
        $this->actingAs($this->teacher)->get(route('assignments.class', $this->class))->assertOk()->assertSee('Can we bring snacks?')->assertSee('Stop commenting');

        // mute the student in this class
        $this->post(route('posts.mute', [$this->class, $this->student]))->assertSessionHas('ok');
        $this->actingAs($this->student)->post(route('posts.comments.store', $this->post), ['body' => 'again'])->assertForbidden();
        $this->actingAs($this->teacher)->post(route('posts.mute', [$this->class, $this->student]));   // unmute
        // turn comments off for the post
        $this->post(route('posts.settings', $this->post), ['track_reads' => 1])->assertSessionHas('ok');
        $this->actingAs($this->student)->post(route('posts.comments.store', $this->post), ['body' => 'x'])->assertForbidden();
        // teacher deletes a comment; another student can't
        $other = User::where('role', 'student')->where('id', '!=', $this->student->id)->first();
        $this->actingAs($other)->delete(route('posts.comments.destroy', $comment))->assertForbidden();
        $this->actingAs($this->teacher)->delete(route('posts.comments.destroy', $comment))->assertSessionHas('ok');
        $this->assertModelMissing($comment);
    }

    public function test_seen_by_is_recorded_only_when_tracked(): void
    {
        $this->actingAs($this->student)->post(route('posts.read', $this->post))->assertNoContent();
        $this->assertSame(1, $this->post->reads()->count());
        $this->actingAs($this->teacher)->get(route('assignments.class', $this->class))->assertSee('Seen by 1 of');
        $this->post->update(['track_reads' => false]);
        $this->post->reads()->delete();
        $this->actingAs($this->student)->post(route('posts.read', $this->post));
        $this->assertSame(0, $this->post->reads()->count());
    }
}

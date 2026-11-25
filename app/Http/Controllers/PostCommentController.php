<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\ClassSubject;
use App\Models\PostComment;
use App\Models\User;
use App\Support\Role;
use App\Support\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Comments and "seen by" on class-card posts. The teacher decides per post whether students can comment
 * and whether views are tracked, can delete any comment, and can stop one student from commenting in the class.
 */
class PostCommentController extends Controller
{
    private function teaches(User $u, ClassSubject $c): bool
    {
        return $u->hasRole(Role::ADMIN) || $c->teacher_id === $u->id;
    }

    private function inClass(User $u, ClassSubject $c): bool
    {
        return $u->role === Role::STUDENT && $u->student?->currentEnrollment()?->section_id === $c->section_id;
    }

    public static function muted(int $classId, int $userId): bool
    {
        return DB::table('class_comment_mutes')->where('class_subject_id', $classId)->where('user_id', $userId)->exists();
    }

    public function store(Request $request, Assignment $assignment)
    {
        abort_unless(School::allows('classwork'), 404);
        $u = $request->user();
        $c = $assignment->classSubject;
        $teacher = $this->teaches($u, $c);
        abort_unless($teacher || $this->inClass($u, $c), 403);
        if (! $teacher) {
            abort_unless($assignment->allow_comments, 403, 'Comments are turned off for this post.');
            abort_if(self::muted($c->id, $u->id), 403, 'Your teacher turned off commenting for you in this class.');
        }
        $d = $request->validate(['body' => 'required|string|max:1000'], ['body.required' => 'Write a comment first.']);
        $assignment->comments()->create(['user_id' => $u->id, 'body' => trim($d['body'])]);

        return back()->with('ok', 'Comment posted.');
    }

    public function destroy(Request $request, PostComment $comment)
    {
        $u = $request->user();
        abort_unless($comment->user_id === $u->id || $this->teaches($u, $comment->assignment->classSubject), 403);
        $comment->delete();

        return back()->with('ok', 'Comment deleted.');
    }

    /** Teacher: comments on/off and "seen by" on/off for one post. */
    public function settings(Request $request, Assignment $assignment)
    {
        abort_unless($this->teaches($request->user(), $assignment->classSubject), 403);
        $assignment->update(['allow_comments' => $request->boolean('allow_comments'), 'track_reads' => $request->boolean('track_reads')]);

        return back()->with('ok', 'Post settings saved.');
    }

    /** Teacher: stop or allow one student commenting anywhere in this class. */
    public function mute(Request $request, ClassSubject $class, User $user)
    {
        abort_unless($this->teaches($request->user(), $class), 403);
        abort_unless($this->inClass($user, $class), 404);
        if (self::muted($class->id, $user->id)) {
            DB::table('class_comment_mutes')->where('class_subject_id', $class->id)->where('user_id', $user->id)->delete();
            $msg = $user->name.' can comment again.';
        } else {
            DB::table('class_comment_mutes')->insert(['class_subject_id' => $class->id, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
            $msg = $user->name.' can no longer comment in this class.';
        }

        return back()->with('ok', $msg);
    }

    /** Student opened the post: record it once (only if the teacher tracks views). */
    public function read(Request $request, Assignment $assignment)
    {
        $u = $request->user();
        if ($assignment->track_reads && $this->inClass($u, $assignment->classSubject)) {
            DB::table('post_reads')->insertOrIgnore(['assignment_id' => $assignment->id, 'user_id' => $u->id, 'read_at' => now()]);
        }

        return response()->noContent();
    }
}

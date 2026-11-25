<?php

namespace Tests\Feature;

use App\Models\ClassSubject;
use App\Models\GradeLevel;
use App\Models\Room;
use App\Models\ScheduleSlot;
use App\Models\Section;
use App\Models\User;
use App\Services\Timetable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TimetableRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    public function test_demo_timetable_has_no_conflicts_and_home_rooms_follow_grade_blocks(): void
    {
        $this->assertCount(0, Timetable::conflicts());
        foreach (Section::with('gradeLevel')->get() as $s) {
            $this->assertMatchesRegularExpression('/^Room '.Timetable::gradeNumber($s->gradeLevel).'\d\d$/', $s->room);
        }
        $this->assertSame(Section::count(), Section::distinct()->count('room'));
        $this->artisan('timetable:check')->assertSuccessful();
    }

    public function test_double_booking_a_room_teacher_or_section_cannot_be_saved(): void
    {
        $slot = ScheduleSlot::with('classSubject')->first();
        $other = ClassSubject::where('section_id', '!=', $slot->classSubject->section_id)->where('teacher_id', '!=', $slot->classSubject->teacher_id)->first();
        $this->expectException(ValidationException::class);
        $other->slots()->create(['day_of_week' => $slot->day_of_week, 'starts_at' => $slot->starts_at, 'ends_at' => $slot->ends_at, 'room' => $slot->room]);
    }

    public function test_teacher_cannot_be_given_a_class_at_a_time_they_already_teach(): void
    {
        $slot = ScheduleSlot::with('classSubject')->first();
        $busyTeacher = $slot->classSubject->teacher_id;
        $clash = ScheduleSlot::with('classSubject')->where('day_of_week', $slot->day_of_week)->where('starts_at', $slot->starts_at)
            ->whereHas('classSubject', fn ($q) => $q->where('teacher_id', '!=', $busyTeacher))->first();
        $this->expectException(ValidationException::class);
        $clash->classSubject->update(['teacher_id' => $busyTeacher]);
    }

    public function test_generate_rooms_for_a_grade_level(): void
    {
        $g = GradeLevel::where('name', 'Grade 3')->first();
        $this->actingAs(User::where('role', 'admin')->first())->post('/rooms/generate', ['grade_level_id' => $g->id, 'spare' => 2])->assertSessionHas('ok');
        $this->assertTrue(Room::where('name', 'Room 301')->where('grade_level_id', $g->id)->exists());
        $this->assertTrue(Room::where('name', 'Room 303')->exists());
        $this->get('/manage/rooms')->assertOk()->assertSee('Generate rooms for a grade level');
    }

    public function test_reset_demo_requires_flag(): void
    {
        $this->artisan('timetable:schedule')->assertFailed();
        $this->artisan('timetable:schedule --reset-demo --force')->assertSuccessful();
        $this->assertCount(0, Timetable::conflicts());
    }
}

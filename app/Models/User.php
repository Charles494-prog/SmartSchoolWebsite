<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Auditable, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'phone', 'is_active', 'must_change_password', 'last_login_at', 'bio', 'avatar_path'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::ADMIN;
    }

    public function isStaff(): bool
    {
        return in_array($this->role, Role::staff(), true);
    }

    public function roleLabel(): string
    {
        return Role::label($this->role);
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function guardian(): HasOne
    {
        return $this->hasOne(Guardian::class);
    }

    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class, 'teacher_id');
    }

    /** Students a parent/guardian account is linked to. */
    public function children()
    {
        return Student::whereHas('guardians', fn ($q) => $q->where('user_id', $this->id))->orderBy('last_name');
    }

    /** Can this user see this student's records? Parents only their own children, students only themselves. */
    public function canViewStudent(Student $student): bool
    {
        return match (true) {
            $this->hasRole(Role::ADMIN, Role::REGISTRAR, Role::CASHIER, Role::GUIDANCE, Role::NURSE, Role::LIBRARIAN) => true,
            $this->role === Role::TEACHER => $this->teachesStudent($student),
            $this->role === Role::STUDENT => $student->user_id === $this->id,
            $this->role === Role::PARENT => $student->guardians()->where('user_id', $this->id)->exists(),
            default => false,
        };
    }

    public function teachesStudent(Student $student): bool
    {
        $sectionIds = $this->teacherSectionIds();

        return $student->enrollments()->whereIn('section_id', $sectionIds)->exists();
    }

    /** Sections where this teacher teaches a subject or is the adviser. */
    public function teacherSectionIds(): array
    {
        return ClassSubject::where('teacher_id', $this->id)->pluck('section_id')
            ->merge(Section::where('adviser_id', $this->id)->pluck('id'))
            ->unique()->values()->all();
    }

    /** Sections relevant to a student/parent (for section announcements). */
    public function visibleSectionIds(): array
    {
        $students = match ($this->role) {
            Role::STUDENT => Student::where('user_id', $this->id)->pluck('id'),
            Role::PARENT => $this->children()->pluck('students.id'),
            Role::TEACHER => collect(),
            default => collect(),
        };
        if ($this->role === Role::TEACHER) {
            return $this->teacherSectionIds();
        }

        return Enrollment::whereIn('student_id', $students)->whereNotNull('section_id')->pluck('section_id')->all();
    }
}

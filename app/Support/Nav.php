<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\PaymentProof;
use App\Models\ServiceRequest;
use App\Models\Setting;
use App\Models\ShopOrder;
use App\Models\User;

/**
 * The menu, in plain words. Each item: label, url, active, icon, desc (shown on the home tiles), color.
 */
class Nav
{
    /** Features each role can never hide (the admin can change this in Settings → Personal setup). */
    public const LOCKED_DEFAULT = [
        'guard' => ['Gate scanner'], 'bookstore' => ['Bookstore'], 'teacher' => ['My classes & grades', 'My classes', 'Advisory attendance'], 'cashier' => ['Transactions'],
        'student' => ['Grades', 'My classes'], 'parent' => ['Grades'], 'registrar' => ['Applications', 'Students', 'Grade approvals'], 'nurse' => ['Clinic'],
        'guidance' => ['Guidance'], 'librarian' => ['Library'], 'hr' => ['Payroll', 'Employees'],
    ];

    public static function locked(string $role): array
    {
        $set = json_decode((string) Setting::get('locked_features'), true);

        return array_merge(['Home', 'How it works'], is_array($set) && isset($set[$role]) ? $set[$role] : (self::LOCKED_DEFAULT[$role] ?? []));
    }

    /** The menu with the person's own hidden features taken out (never the locked ones). */
    public static function for(User $u): array
    {
        $all = self::full($u);
        $hide = array_diff((array) $u->pref('hidden', []), self::locked($u->role));
        if (! $hide || $u->role === Role::ADMIN) {
            return $all;
        }

        return collect($all)->map(fn ($items) => array_values(array_filter($items, fn ($i) => ! in_array($i['label'], $hide, true))))->filter()->all();
    }

    /** Every feature this role can use (before personal choices). */
    public static function full(User $u): array
    {
        $r = $u->role;
        $is = fn (string ...$roles) => in_array($r, $roles, true);
        $item = fn (string $label, string $route, string $icon, string $desc, string $color = 'indigo', string|array $pattern = '') => [
            'label' => $label, 'url' => route($route), 'active' => request()->routeIs($pattern ?: $route), 'icon' => $icon, 'desc' => $desc, 'color' => $color,
        ];
        $crud = fn (string $label, string $slug, string $icon, string $desc, string $color = 'slate') => [
            'label' => $label, 'url' => route('crud.index', $slug), 'active' => request()->route('resource') === $slug, 'icon' => $icon, 'desc' => $desc, 'color' => $color,
        ];

        if ($is(Role::STUDENT, Role::PARENT)) {
            // Grouped by office, so families know who handles what.
            return array_filter([
                '' => [$item('Home', 'portal', 'home', 'Summary and news')],
                'My classes' => array_values(array_filter([
                    $item('Grades', 'portal.grades', 'award', 'Grades per subject and report card', 'amber'),
                    $item('Attendance', 'portal.attendance', 'check-square', 'Days present, late and absent', 'emerald'),
                    $item('Class schedule', 'portal.schedule', 'clock', 'Weekly timetable', 'sky'),
                    School::allows('classwork') ? $item('My classes', 'portal.classes', 'clipboard', 'Class cards: announcements, documents and work', 'violet', 'portal.class*') : null,
                ])),
                'Finance' => [$item('Payments', 'portal.account', 'wallet', 'Balance, pay online, receipts', 'rose')],
                'Registrar' => [
                    $item('Request documents', 'portal.services', 'file', 'TOR, certificates and other documents', 'sky', 'portal.services*'),
                    $item('Print ID card', 'portal.id', 'qr', 'Your QR ID card for the gate and class', 'emerald'),
                ],
                'Bookstore' => [$item('Bookstore', 'portal.shop', 'tag', 'Order books, uniforms and school merch', 'amber')],
                'School news' => [
                    $item('Announcements', 'announcements.index', 'megaphone', 'News from the school', 'indigo', 'announcements.*') + ['badge' => Announcement::unreadCount($u)],
                    $item('Calendar', 'calendar', 'calendar', 'Holidays, exams and events', 'sky'),
                ],
                'Help' => [$item('How it works', 'guide', 'sparkles', 'How to use this portal', 'violet')],
            ]);
        }

        if ($is(Role::BOOKSTORE)) {
            return ['' => [
                $item('Home', 'dashboard', 'home', 'Overview'),
                $item('Bookstore', 'shop.manage', 'tag', 'Approve orders, release items, post merch', 'amber', 'shop.manage') + ['badge' => ShopOrder::where('status', 'placed')->count()],
                $item('Announcements', 'announcements.index', 'megaphone', 'News from the school', 'indigo', 'announcements.*') + ['badge' => Announcement::unreadCount($u)],
            ]];
        }

        if ($is(Role::GUARD)) {
            return ['' => [
                $item('Gate scanner', 'attendance.scan', 'qr', 'Scan students in and out at the gate', 'emerald'),
            ]];
        }

        $g = ['' => [$item('Home', 'dashboard', 'home', 'Overview')]];

        $students = [];
        if ($is(Role::ADMIN, Role::REGISTRAR)) {
            $students[] = $item('Applications', 'applications.index', 'inbox', 'Review online admission forms', 'violet', 'applications.*');
        }
        if ($is(Role::ADMIN, Role::REGISTRAR, Role::CASHIER, Role::NURSE, Role::GUIDANCE)) {
            $students[] = $item('Students', 'students.index', 'users', 'Find a student, profiles and guardians', 'indigo', 'students.*');
        }
        if ($is(Role::ADMIN, Role::REGISTRAR)) {
            $students[] = $item('Classes & sections', 'sections.index', 'layers', 'Sections, teachers and schedules', 'sky', 'sections.*');
            if ($r === Role::REGISTRAR) {
                $students[] = $crud('Rooms', 'rooms', 'home', 'Classrooms and how many seats each has');
            }
            $students[] = $item('Document requests', 'services.manage', 'file', 'TOR, certificates – set fees and release', 'sky', 'services.*') + ['badge' => ServiceRequest::where('status', 'requested')->count()];
            $students[] = $item('Enter LRNs', 'students.lrn', 'key', 'Type or paste each student\'s DepEd LRN', 'indigo');
            $students[] = $item('QR ID cards', 'qr.cards', 'qr', 'Print students\' QR ID cards to hand out', 'emerald');
            $students[] = $item('Move up a grade', 'promotion.index', 'trending', 'End-of-year promotion', 'emerald', 'promotion.*');
            $students[] = $item('Assign sections', 'allocation.index', 'layers', 'Put a grade\'s students into sections (random, by choice or by hand)', 'emerald', 'allocation.*');
            $students[] = $crud('Transfers', 'transfers', 'repeat', 'Students who moved schools');
        }
        if ($students) {
            $g['Students'] = $students;
        }

        $acad = [];
        if ($r === Role::REGISTRAR) {
            // Registrar: records only – approve submitted grades, keep schedules and subjects. No teaching tools.
            $acad[] = $item('Grade approvals', 'approvals.index', 'check-square', 'Approve grades teachers submitted', 'amber', ['approvals.*', 'gradebook.*']);
            $acad[] = $item('Timetable', 'timetable', 'clock', 'Weekly schedule by room, section or teacher', 'sky');
        }
        if ($is(Role::TEACHER, Role::ADMIN)) {
            if ($r === Role::TEACHER && School::allows('classwork')) {
                // Online: one place per class – posts, classwork and its gradebook.
                $acad[] = $item('My classes', 'assignments.index', 'book-open', 'Class cards: posts, classwork and grades', 'amber', ['assignments.*', 'gradebook.*']);
            } else {
                $acad[] = $item($r === Role::TEACHER ? 'My classes & grades' : 'Grades', 'gradebook.index', 'book-open', 'Enter scores and compute grades', 'amber', 'gradebook.*');
                if (School::allows('classwork')) {
                    $acad[] = $item('Class cards', 'assignments.index', 'clipboard', 'Each class: posts, classwork and submissions', 'violet', 'assignments.*');
                }
            }
            if ($r === Role::ADMIN) {
                $acad[] = $item('Grade approvals', 'approvals.index', 'check-square', 'Approve grades teachers submitted', 'amber', 'approvals.*');
            }
            $acad[] = $item('Honor roll', 'honors', 'award', 'Ranking and honors per section', 'amber');
            $acad[] = $item('Timetable', 'timetable', 'clock', 'Weekly schedule by room, section or teacher', 'sky');
        }
        if ($is(Role::ADMIN, Role::REGISTRAR)) {
            $acad[] = $crud('Subjects', 'subjects', 'book', 'Subjects and grading weights');
        }
        if ($acad) {
            $g[$r === Role::REGISTRAR ? 'Records' : 'Teaching'] = $acad;
        }

        if ($is(Role::TEACHER, Role::ADMIN)) {   // registrar keeps records, not attendance
            $g['Attendance'] = [
                $item($r === Role::TEACHER ? 'Advisory attendance' : 'Daily attendance', 'attendance.sheet', 'check-square', 'Daily attendance list of a section (advisers)', 'emerald', 'attendance.sheet'),
                $item($r === Role::TEACHER ? 'Scan QR in my class' : 'QR scanner', 'attendance.scan', 'qr', $r === Role::TEACHER ? 'Scan students into the subject you are teaching' : 'Gate check-in/out or a class', 'emerald'),
                $item('Attendance report', 'attendance.report', 'chart', 'Monthly summary per section', 'emerald'),
            ];
        }

        if ($is(Role::CASHIER, Role::ADMIN)) {
            $g['Payments'] = [
                $item('Transactions', 'finance.index', 'wallet', 'Find a student, take a payment, receipts and balances', 'rose', ['finance.index', 'finance.show', 'finance.payments', 'finance.overdue']),
                ...(School::allows('parent_accounts') ? [$item('Online payments', 'finance.proofs', 'receipt', 'Check payments families sent online', 'emerald', 'finance.proofs*') + ['badge' => PaymentProof::where('status', 'pending')->count()]] : []),
                $item('Bookstore orders to pay', 'finance.bookstore', 'tag', 'Bookstore orders waiting for payment (walk-ins first)', 'amber', 'finance.bookstore'),
                $crud('Fees', 'fee-items', 'tag', 'Tuition and other fees'),
                $crud('Discounts & scholarships', 'discounts', 'percent', 'Discounts and scholarships'),
                $crud('Payment plans', 'payment-plans', 'calendar', 'How tuition is divided into payments'),
            ];
        }

        $comm = [
            $item('Announcements', 'announcements.index', 'megaphone', 'News for parents, students and staff', 'indigo', 'announcements.*') + ['badge' => Announcement::unreadCount($u)],
            $item('Calendar', 'calendar', 'calendar', 'Holidays, exams and events', 'sky'),
        ];
        if ($is(Role::ADMIN, Role::REGISTRAR)) {
            $comm[] = $crud('Add events', 'events', 'calendar-plus', 'Put dates on the school calendar');
        }
        $g['Communication'] = $comm;

        $more = [];
        if ($is(Role::LIBRARIAN, Role::ADMIN)) {
            $more[] = $item('Library', 'library.index', 'library', 'Books, borrowing and returns', 'amber', 'library.*');
        }
        if ($is(Role::NURSE, Role::ADMIN)) {
            $more[] = $crud('Clinic', 'clinic-visits', 'heart', 'Clinic visits (private)', 'rose');
        }
        if ($is(Role::GUIDANCE)) {
            $more[] = $crud('Guidance', 'guidance-records', 'compass', 'Counseling notes (confidential)', 'violet');
        }
        if ($is(Role::HR, Role::ADMIN)) {
            $more[] = $crud('Employees', 'employees', 'briefcase', 'Staff records', 'slate');
            $more[] = $item('Payroll', 'payroll.index', 'banknote', 'Salaries and payslips', 'emerald', 'payroll.*');
        }
        if ($more) {
            $g['Services'] = $more;
        }

        $g['Help'] = [$item('How it works', 'guide', 'sparkles', 'The school flow step by step, and who does what', 'violet')];

        if ($is(Role::ADMIN)) {
            $g['School setup'] = [
                $item('School settings', 'settings.edit', 'settings', 'Name, logo, grading and rules'),
                $item('Bookstore', 'shop.manage', 'tag', 'Bookstore items and orders', 'amber', 'shop.manage'),
                $item('Payment methods', 'channels.index', 'banknote', 'GCash / bank accounts and QR codes for online payments', 'emerald', 'channels.*'),
                $crud('School years', 'school-years', 'calendar', 'Start a new school year'),
                $crud('Grade levels', 'grade-levels', 'cap', 'Kinder to Grade 12'),
                $crud('Rooms', 'rooms', 'home', 'Classrooms and how many seats each has'),
                $crud('User accounts', 'users', 'shield', 'Staff logins and roles'),
                $item('Activity log', 'audit', 'history', 'Who changed what, and when'),
                $item('Backups', 'backups.index', 'database', 'Save a copy of all data'),
            ];
        }

        return $g;
    }

    /** The (at most 6) items shown in the sidebar. Everything else is in Ctrl+K and on Home. */
    public static function primary(User $u): array
    {
        $all = collect(self::for($u))->flatten(1)->keyBy('label');
        $pick = match ($u->role) {
            Role::ADMIN => ['Home', 'Students', 'Classes & sections', 'Grades', 'Transactions', 'School settings'],
            Role::REGISTRAR => ['Home', 'Applications', 'Students', 'Classes & sections', 'Document requests', 'Grade approvals'],
            Role::TEACHER => ['Home', 'My classes & grades', 'Scan QR in my class', 'Advisory attendance', 'My classes', 'Class cards', 'Messages'],
            Role::GUARD => ['Gate scanner', 'Messages'],
            Role::CASHIER => ['Home', 'Transactions', 'Online payments', 'Students'],
            Role::STUDENT, Role::PARENT => ['Home', 'Grades', 'Attendance', 'Class schedule', 'Payments', 'Messages'],
            default => [],
        };
        $items = collect($pick)->map(fn ($l) => $all->get($l))->filter();
        if ($items->count() < 3) {
            $items = $all->values()->take(6);
        }

        return $items->values()->all();
    }

    /** Items for the "What would you like to do?" section, grouped by category (Home left out). */
    public static function groupedTiles(User $u): array
    {
        return collect(self::for($u))
            ->map(fn ($items) => array_values(array_filter($items, fn ($i) => $i['icon'] !== 'home' || $i['label'] !== 'Home')))
            ->filter()
            ->mapWithKeys(fn ($items, $group) => [$group ?: 'General' => $items])
            ->all();
    }

    /** All items except Home, for the "What would you like to do?" tiles. */
    public static function tiles(User $u): array
    {
        return collect(self::for($u))->flatten(1)->filter(fn ($i) => $i['icon'] !== 'home')->values()->all();
    }
}

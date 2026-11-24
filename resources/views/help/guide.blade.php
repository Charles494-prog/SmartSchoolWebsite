@php
    $u = auth()->user();
    $r = $u->role;
    $link = fn ($name, ...$p) => \Illuminate\Support\Facades\Route::has($name) ? route($name, ...$p) : null;
    $steps = [
        ['Set up the school', 'admin', 'Admin', 'settings',
            'Enter the school name and logo, start the school year, list the classrooms with their seats (Rooms), check the grade levels and subjects (with how quizzes, projects and exams are weighted), then create accounts for the registrar, teachers, cashier and other staff.',
            [['School settings', $link('settings.edit')], ['Subjects', $link('crud.index', 'subjects')], ['Staff accounts', $link('crud.index', 'users')]]],
        ['Students apply (or are added)', 'registrar', 'Families → Registrar', 'inbox',
            'Families fill in the online form at /apply and upload documents. The registrar opens Applications and clicks Approve. That one click creates the student record AND the parent\'s login (sent by email). Walk-in students can be added directly in Students → Add student.',
            [['Applications', $link('applications.index')], ['Public application form', $link('apply.create')]]],
        ['Make classes and schedules', 'registrar', 'Registrar (or Admin)', 'layers',
            'In Classes & sections create each section (e.g. Grade 7 – Rizal), pick the adviser, choose the teacher for each subject, add the class times, then add the students. The timetable is built from this.',
            [['Classes & sections', $link('sections.index')], ['Timetable', $link('timetable')]]],
        ['Print QR ID cards', 'registrar', 'Registrar (or Admin)', 'qr',
            'Open QR ID cards and download a PDF for a whole section (10 cards per page), or one student. Print, cut and hand them out. Students bring the card every day.',
            [['QR ID cards', $link('qr.cards')]]],
        ['Fees and payments', 'cashier', 'Cashier', 'wallet',
            'For each student, choose a payment plan and any discount, then record payments to issue an official receipt. Parents see their balance on their phone.',
            [['Student balances', $link('finance.index')]]],
        ['Attendance every day', 'teacher', 'Guard · Teachers · Adviser', 'check-square',
            'At the gate the guard scans each QR card: first scan = IN, scan again when leaving = OUT. Each subject teacher scans the class in their own subject. The adviser checks the daily list and fixes anyone who forgot their card. Parents of late or absent students are notified automatically.',
            [['Scan QR in my class', $link('attendance.scan')], ['Advisory attendance', $link('attendance.sheet')]]],
        ['Grades during the term', 'teacher', 'Teacher → Registrar', 'book-open',
            'Teachers add quizzes, projects and exams and type the scores; the grade is computed automatically. When the term ends the teacher clicks Submit; the registrar approves. Parents and students then see the grades.',
            [['Grades', $link('gradebook.index')]]],
        ['End of the school year', 'registrar', 'Registrar', 'award',
            'Print report cards, see the honor roll, then use Move up a grade to promote passing students to the next school year.',
            [['Honor roll', $link('honors')], ['Move up a grade', $link('promotion.index')]]],
    ];
    $mine = fn ($who) => $r === 'admin' || $r === $who || ($who === 'registrar' && $r === 'admin');
@endphp
<x-layouts.app title="How it works">
    <x-page-header title="How it works" subtitle="The whole school year in 8 steps. Steps for your account are highlighted." />

    @if($u->hasRole('student', 'parent'))
        <div class="card mb-8">
            <h2 class="h2 mb-3">Your portal, in short</h2>
            <ul class="space-y-3 text-sm">
                <li class="flex gap-3"><x-icon name="qr" class="h-5 w-5 shrink-0 text-emerald-500" /> Bring the QR ID card to school. The <b>teacher</b> scans it to mark attendance – you don't need to do anything on the website.</li>
                <li class="flex gap-3"><x-icon name="award" class="h-5 w-5 shrink-0 text-amber-500" /> <b>Grades</b> shows scores per subject as soon as teachers post them, and the report card.</li>
                <li class="flex gap-3"><x-icon name="check-square" class="h-5 w-5 shrink-0 text-emerald-500" /> <b>Attendance</b> shows every present, late and absent day. Parents also get a notice when the child is late or absent.</li>
                <li class="flex gap-3"><x-icon name="wallet" class="h-5 w-5 shrink-0 text-rose-500" /> <b>Payments</b> shows the balance, due dates and receipts. Pay at the cashier.</li>
                <li class="flex gap-3"><x-icon name="message" class="h-5 w-5 shrink-0 text-brand-500" /> <b>Messages</b> lets you write to the adviser and teachers.</li>
            </ul>
        </div>
    @endif

    <ol class="relative space-y-4 border-l-2 border-dashed border-slate-200 pl-6 dark:border-white/10">
        @foreach($steps as $i => [$title, $who, $whoLabel, $icon, $text, $links])
            @php $me = $mine($who) || ($r === 'teacher' && $who === 'teacher'); @endphp
            <li class="relative">
                <span class="absolute top-5 -left-[2.4rem] flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold ring-4 ring-slate-50 dark:ring-slate-950 {{ $me ? 'bg-brand-600 text-white' : 'bg-slate-200 text-slate-600' }}">{{ $i + 1 }}</span>
                <div class="card {{ $me ? 'ring-2 ring-brand-200' : 'opacity-80' }}">
                    <div class="flex flex-wrap items-center gap-3">
                        <x-icon :name="$icon" class="h-5 w-5 text-brand-500" />
                        <h2 class="h2 flex-1">{{ $title }}</h2>
                        <span class="badge {{ $me ? 'bg-brand-100 text-brand-700' : 'bg-slate-100 text-slate-500' }}">{{ $whoLabel }}{{ $me ? ' · you' : '' }}</span>
                    </div>
                    <p class="mt-2 text-sm text-slate-600">{{ $text }}</p>
                    @if($me)
                        <div class="mt-4 flex flex-wrap gap-2">
                            @foreach($links as [$label, $url])@if($url)<a href="{{ $url }}" class="btn btn-sm">{{ $label }} <x-icon name="chevron" class="h-3.5 w-3.5" /></a>@endif @endforeach
                        </div>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>

    <div class="mt-10 grid gap-4 lg:grid-cols-2">
        <div class="card">
            <h2 class="h2 mb-4">Who does what</h2>
            <dl class="space-y-3 text-sm">
                @foreach([
                    'Admin' => 'Sets up the school, creates staff accounts, can do everything.',
                    'Registrar' => 'Applications, students, sections, subjects, schedules, QR cards, approving grades, report cards.',
                    'Teacher' => 'Scans QR codes in their own classes, enters scores for their own subjects only, gives assignments, messages parents. Advisers also keep the daily attendance list.',
                    'Security guard' => 'Only the gate scanner: scans students IN and OUT. Cannot open anything else.',
                    'Cashier' => 'Fees, discounts, payments and receipts.',
                    'Parent / Student' => 'Only views: grades, attendance, schedule, balance, assignments, messages. They do not scan.',
                    'Librarian, Nurse, Guidance, HR' => 'Library, clinic visits, counseling notes, and payroll.',
                ] as $who => $what)
                    <div class="flex gap-3"><dt class="w-36 shrink-0 font-semibold">{{ $who }}</dt><dd class="text-slate-600">{{ $what }}</dd></div>
                @endforeach
            </dl>
        </div>
        <div class="card">
            <h2 class="h2 mb-4">Common questions</h2>
            <div class="space-y-2 text-sm">
                @foreach([
                    'Who creates accounts?' => 'The Admin creates staff accounts (Staff accounts). Parent accounts are created automatically when an application is approved or when the registrar adds a guardian with an email. Student logins are created from the student\'s profile.',
                    'Where do I get the QR codes?' => 'QR ID cards (registrar/admin). Download per section or per student, print and hand out.',
                    'Who scans the QR codes?' => 'The guard at the gate (IN in the morning, OUT when leaving) and each subject teacher in their own class. A USB QR scanner or a phone/laptop camera both work.',
                    'Who makes subjects and schedules?' => 'Subjects: Admin or Registrar. Class schedules: Registrar or Admin, inside each section in Classes & sections.',
                    'I lost my password' => 'Ask the Admin to set a new one in Staff accounts; you will be asked to change it after logging in.',
                ] as $q => $a)
                    <details class="rounded-xl p-3 ring-1 ring-slate-200 open:bg-slate-50"><summary class="cursor-pointer font-semibold">{{ $q }}</summary><p class="mt-2 text-slate-600">{{ $a }}</p></details>
                @endforeach
            </div>
        </div>
    </div>
</x-layouts.app>

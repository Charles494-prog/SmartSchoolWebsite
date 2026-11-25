@php
    $month = now()->startOfMonth();
    $gridStart = $month->copy()->startOfWeek(\Carbon\Carbon::SUNDAY);
    $gridEnd = $month->copy()->endOfMonth()->endOfWeek(\Carbon\Carbon::SATURDAY);
    $eventDays = $events->flatMap(function ($e) {
        $days = [];
        for ($d = $e->starts_on->copy(); $d->lte($e->ends_on ?? $e->starts_on); $d->addDay()) { $days[] = $d->toDateString(); }
        return collect($days)->mapWithKeys(fn ($d) => [$d => $e]);
    });
    $dot = ['holiday' => 'bg-rose-500', 'exam' => 'bg-amber-500', 'meeting' => 'bg-sky-500', 'event' => 'bg-emerald-500'];
    $upcoming = $events->filter(fn ($e) => ($e->ends_on ?? $e->starts_on)->gte(today()))->take(5);
    $guide = [
        ['qr', 'Bring your QR ID card', 'The teacher scans it to mark attendance – you don\'t need to do anything on the website.', null],
        ['award', 'Grades', 'Scores per subject as soon as teachers post them, and the report card.', route('portal.grades')],
        ['check-square', 'Attendance', 'Every present, late and absent day. Parents also get a notice when the child is late or absent.', route('portal.attendance')],
        ['wallet', 'Payments', 'The balance, due dates and receipts. Pay at the cashier.', route('portal.account')],
        ['message', 'Messages', 'Write to the adviser and teachers.', route('messages.index')],
    ];
@endphp
<x-layouts.app title="Home">
    @include('portal._switch')
    @if(($todo ?? collect())->isNotEmpty())
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-3 flex items-center gap-2"><h2 class="text-lg font-semibold">To do</h2><span class="rounded-full bg-amber-100 px-2 text-xs font-bold text-amber-800">{{ $todo->count() }}</span>
                <a href="{{ route('welcome') }}" class="ml-auto text-xs text-slate-400 hover:text-slate-600">Change</a></div>
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach($todo as $t)
                    <li><a href="{{ $t->type === 'quiz' ? route('quiz.take', $t) : route('portal.class', $t->class_subject_id) }}" class="flex items-center gap-3 py-2.5 hover:text-brand-600">
                        <span class="h-2 w-2 shrink-0 rounded-full {{ $t->due_at->isPast() ? 'bg-rose-500' : ($t->due_at->diffInDays() < 2 ? 'bg-amber-500' : 'bg-slate-300') }}"></span>
                        <span class="min-w-0 flex-1"><span class="block truncate font-medium">{{ $t->title }}</span><span class="text-xs text-slate-500">{{ $t->classSubject->subject->name }}</span></span>
                        <span class="text-xs {{ $t->due_at->isPast() ? 'font-semibold text-rose-600' : 'text-slate-500' }}">{{ $t->due_at->isPast() ? 'Late · was due '.$t->due_at->format('M d') : 'Due '.$t->due_at->format('M d, g:i A') }}</span>
                    </a></li>
                @endforeach
            </ul>
        </div>
    @endif
    @if($child)
        <x-page-header :title="auth()->user()->role === 'parent' ? 'Welcome, '.auth()->user()->name : 'Welcome, '.$child->first_name"
                       :subtitle="$child->full_name.' · '.($enrollment?->gradeLevel?->name ?? '').($enrollment?->section ? ' – '.$enrollment->section->name : '')" />

        <div class="mb-8 grid gap-4 sm:grid-cols-3">
            <x-stat icon="award" label="General average" :value="$report['general_average'] ?? 'No grades yet'" tone="brand" :href="route('portal.grades')" />
            <x-stat icon="check-square" :label="'Absent this month · '.$attendance->where('status', 'late')->count().' late'" :value="$attendance->where('status', 'absent')->count()" :href="route('portal.attendance')" />
            <x-stat icon="wallet" label="Balance" :value="$balance === null ? '—' : \App\Services\Billing::peso($balance)" :href="route('portal.account')" />
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
            {{-- Announcements feed --}}
            <div class="space-y-4">
                <h2 class="text-lg font-semibold text-slate-900">Announcements</h2>
                @forelse($announcements as $a)
                    @php $initials = collect(explode(' ', $a->author?->name ?? 'School'))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode(''); @endphp
                    <article class="card p-0">
                        <header class="flex items-center gap-3 px-5 pt-4">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-700">{{ $initials }}</span>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-semibold text-slate-900">{{ $a->author?->name ?? \App\Models\Setting::get('school_name') }}</div>
                                <div class="flex flex-wrap items-center gap-1 text-xs text-slate-500">
                                    <time datetime="{{ $a->published_at?->toIso8601String() }}" title="{{ $a->published_at?->format('M j, Y g:i A') }}">{{ $a->published_at?->diffForHumans() }}</time>
                                    <span aria-hidden="true">·</span>
                                    <span>{{ $a->section ? $a->section->label() : \App\Models\Announcement::AUDIENCES[$a->audience] }}</span>
                                </div>
                            </div>
                            @if($a->is_pinned)<span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600"><x-icon name="pin" class="h-3.5 w-3.5" /> Pinned</span>@endif
                        </header>
                        <div class="px-5 pt-3 pb-5">
                            <h3 class="font-semibold text-slate-900">{{ $a->title }}</h3>
                            <p class="mt-1 text-[15px] leading-relaxed whitespace-pre-line text-slate-700">{{ $a->body }}</p>
                        </div>
                    </article>
                @empty
                    <div class="card"><x-empty icon="megaphone" title="No announcements yet" text="News from the school and your teachers will show up here." /></div>
                @endforelse
            </div>

            <div class="space-y-6">
                {{-- Calendar --}}
                <div class="card p-5">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="font-semibold text-slate-900">{{ $month->format('F Y') }}</h2>
                        <a href="{{ route('calendar') }}" class="text-sm font-medium text-brand-600 hover:underline">Full calendar</a>
                    </div>
                    <div class="grid grid-cols-7 gap-1 text-center text-[11px] font-semibold text-slate-400">
                        @foreach(['S', 'M', 'T', 'W', 'T', 'F', 'S'] as $d)<div>{{ $d }}</div>@endforeach
                    </div>
                    <div class="mt-1 grid grid-cols-7 gap-1 text-center text-sm">
                        @for($d = $gridStart->copy(); $d->lte($gridEnd); $d->addDay())
                            @php $ev = $eventDays[$d->toDateString()] ?? null; @endphp
                            <div class="relative flex h-9 flex-col items-center justify-center rounded-lg {{ $d->month !== $month->month ? 'text-slate-300' : 'text-slate-700' }} {{ $d->isToday() ? 'bg-brand-600 font-bold text-white' : '' }}" @if($ev) title="{{ $ev->title }}" @endif>
                                {{ $d->day }}
                                @if($ev)<span class="absolute bottom-1 h-1.5 w-1.5 rounded-full {{ $d->isToday() ? 'bg-white' : ($dot[$ev->type] ?? 'bg-slate-400') }}"></span>@endif
                            </div>
                        @endfor
                    </div>
                    <ul class="mt-4 space-y-2 border-t border-slate-100 pt-4">
                        @forelse($upcoming as $e)
                            <li class="flex items-start gap-3">
                                <div class="w-11 shrink-0 rounded-lg bg-slate-50 py-1 text-center ring-1 ring-slate-200">
                                    <div class="text-[10px] font-semibold text-slate-500 uppercase">{{ $e->starts_on->format('M') }}</div>
                                    <div class="text-base leading-none font-bold text-slate-900">{{ $e->starts_on->format('j') }}</div>
                                </div>
                                <div class="min-w-0 pt-0.5">
                                    <div class="truncate text-sm font-medium text-slate-900">{{ $e->title }}</div>
                                    <div class="flex items-center gap-1.5 text-xs text-slate-500"><span class="h-1.5 w-1.5 rounded-full {{ $dot[$e->type] ?? 'bg-slate-400' }}"></span>{{ \App\Models\Event::TYPES[$e->type] ?? $e->type }}{{ $e->ends_on ? ' · until '.$e->ends_on->format('M j') : '' }}</div>
                                </div>
                            </li>
                        @empty
                            <li class="text-sm text-slate-500">Nothing coming up.</li>
                        @endforelse
                    </ul>
                </div>

                {{-- Your portal, in short --}}
                <div class="card p-5">
                    <h2 class="mb-3 font-semibold text-slate-900">Your portal, in short</h2>
                    <ul class="space-y-1">
                        @foreach($guide as [$icon, $title, $text, $url])
                            <li>
                                <{{ $url ? 'a href='.$url : 'div' }} class="flex gap-3 rounded-xl p-2.5 {{ $url ? 'hover:bg-slate-50 dark:hover:bg-white/5' : '' }}">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300"><x-icon :name="$icon" class="h-[18px] w-[18px]" /></span>
                                    <span class="min-w-0"><span class="block text-sm font-semibold text-slate-900">{{ $title }}</span><span class="block text-xs leading-relaxed text-slate-500">{{ $text }}</span></span>
                                </{{ $url ? 'a' : 'div' }}>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif
</x-layouts.app>

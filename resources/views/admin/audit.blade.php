@php
    use App\Support\AuditPresenter as AP;
    $tone = ['created' => 'bg-emerald-50 text-emerald-700', 'updated' => 'bg-sky-50 text-sky-700', 'deleted' => 'bg-rose-50 text-rose-700'];
    $byDay = $entries->groupBy(fn ($e) => $e['time']->isToday() ? 'Today' : ($e['time']->isYesterday() ? 'Yesterday' : $e['time']->format('l, M j, Y')));
@endphp
<x-layouts.app title="Activity log">
    <x-page-header title="Activity log" subtitle="Who changed what, and when. Every change keeps its old and new value." />

    <form class="card mb-6 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_11rem_auto]">
        <select name="user_id" class="input" aria-label="Person"><option value="">Everyone</option>@foreach($users as $k => $v)<option value="{{ $k }}" @selected(request('user_id') == $k)>{{ $v }}</option>@endforeach</select>
        <select name="auditable_type" class="input" aria-label="Kind of record"><option value="">All kinds of records</option>@foreach($types as $t)<option value="{{ $t }}" @selected(request('auditable_type') === $t)>{{ ucfirst(AP::TYPE_LABELS[$t] ?? \Illuminate\Support\Str::headline($t)) }}</option>@endforeach</select>
        <select name="action" class="input" aria-label="What happened"><option value="">Everything that happened</option>@foreach(AP::ACTIONS as $k => $v)<option value="{{ $k }}" @selected(request('action') === $k)>{{ $v }}</option>@endforeach</select>
        <input type="date" name="from" value="{{ request('from') }}" class="input" aria-label="From date">
        <div class="flex gap-2"><button class="btn btn-primary">Show</button>@if(request()->hasAny(['user_id', 'auditable_type', 'action', 'from', 'id']))<a href="{{ route('audit') }}" class="btn">Clear</a>@endif</div>
    </form>

    @forelse($byDay as $day => $items)
        <h2 class="mb-2 mt-6 text-xs font-semibold tracking-wider text-slate-500 uppercase first:mt-0">{{ $day }}</h2>
        <ol class="card divide-y divide-slate-100 p-0 dark:divide-white/5">
            @foreach($items as $e)
                <li x-data="{ open: false }" class="px-4 py-3 sm:px-5">
                    <div class="flex items-start gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600 dark:bg-white/10 dark:text-slate-300">{{ $e['initials'] ?: '–' }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-slate-700 dark:text-slate-300">
                                <span class="font-semibold text-slate-900">{{ $e['who'] }}</span>
                                {{ $e['sentence'] }}
                                @if($e['subject'])<span class="font-medium text-slate-900">{{ $e['subject'] }}</span>@endif
                                @if(isset($tone[$e['action']]))<span class="ml-1 rounded-full px-2 py-0.5 text-[11px] font-medium {{ $tone[$e['action']] }}">{{ AP::ACTIONS[$e['action']] }}</span>@endif
                            </p>
                            @if($e['action'] === 'updated' && $e['changes'])
                                <ul class="mt-1.5 space-y-1 text-sm">
                                    @foreach(array_slice($e['changes'], 0, 4) as $c)
                                        <li class="text-slate-600 dark:text-slate-400">{{ $c['label'] }}: @unless($c['plain'] ?? false)<span class="text-slate-400 line-through">{{ $c['old'] }}</span> <span aria-hidden="true">→</span> @endunless<span class="font-medium text-slate-900 dark:text-white">{{ $c['new'] }}</span></li>
                                    @endforeach
                                </ul>
                            @endif
                            @if(($e['action'] !== 'updated' && $e['changes']) || count($e['changes']) > 4)
                                <button type="button" class="mt-1 min-h-8 text-xs font-medium text-brand-700 hover:underline dark:text-brand-300" x-on:click="open = !open" x-text="open ? 'Hide details' : 'Show details'"></button>
                                <dl x-show="open" x-cloak class="mt-2 grid gap-x-6 gap-y-1 rounded-lg bg-slate-50 p-3 text-xs sm:grid-cols-2 dark:bg-white/5">
                                    @foreach($e['changes'] as $c)<div class="flex gap-2"><dt class="text-slate-500">{{ $c['label'] }}</dt><dd class="font-medium text-slate-800 dark:text-slate-200">@unless($c['plain'] ?? false){{ $c['old'] }} → @endunless{{ $c['new'] }}</dd></div>@endforeach
                                </dl>
                            @endif
                        </div>
                        <time class="shrink-0 text-xs text-slate-500" datetime="{{ $e['time']->toIso8601String() }}" title="{{ $e['time']->format('M j, Y g:i:s A') }} · {{ $e['log']->ip }}">{{ $e['time']->format('g:i A') }}</time>
                    </div>
                </li>
            @endforeach
        </ol>
    @empty
        <div class="card"><x-empty icon="history" title="Nothing here yet" text="Changes made in SmartSchool will show up here." /></div>
    @endforelse
    <div class="mt-6">{{ $logs->links() }}</div>
</x-layouts.app>

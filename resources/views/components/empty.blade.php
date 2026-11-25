@props(['icon' => 'sparkles', 'title' => 'Nothing here yet', 'text' => null, 'action' => null, 'href' => null, 'steps' => [], 'open' => false])
{{-- Empty state: what is missing, the one button that fixes it, and (optional) numbered steps showing how. --}}
<div class="flex flex-col items-center px-6 py-12 text-center" x-data="{ how: @js((bool) $open) }">
    <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 text-brand-500"><x-icon :name="$icon" class="h-7 w-7" /></div>
    <div class="font-semibold text-slate-900">{{ $title }}</div>
    @if($text)<p class="mt-1 max-w-md text-sm text-slate-500">{{ $text }}</p>@endif
    @if($action || $steps || trim($slot))
        <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
            @if($action && $href)<a href="{{ $href }}" class="btn btn-primary min-h-11"><x-icon name="plus" class="h-4 w-4" /> {{ $action }}</a>@endif
            {{ $slot }}
            @if($steps)
                <button type="button" class="btn min-h-11" x-on:click="how = !how" :aria-expanded="how"><x-icon name="sparkles" class="h-4 w-4 text-violet-500" /> <span x-text="how ? 'Hide steps' : 'Show me how'">Show me how</span></button>
            @endif
        </div>
    @endif
    @if($steps)
        <ol x-show="how" x-cloak x-transition.opacity.duration.250ms class="mt-5 w-full max-w-md space-y-2 text-left">
            @foreach($steps as $i => $step)
                <li class="flex gap-3 rounded-xl bg-slate-50 p-3 text-sm text-slate-700 dark:bg-white/5">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-600 text-xs font-bold text-white">{{ $i + 1 }}</span>
                    <span class="pt-0.5">{!! $step !!}</span>
                </li>
            @endforeach
        </ol>
    @endif
</div>

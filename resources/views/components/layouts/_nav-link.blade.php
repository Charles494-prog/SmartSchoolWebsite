<a href="{{ $i['url'] }}" class="group flex min-h-10 items-center gap-3 rounded-xl px-3 py-2 text-sm transition {{ $i['active'] ? 'bg-brand-50 font-semibold text-brand-700 dark:bg-brand-500/15 dark:text-brand-200' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-white' }}" @if($i['active']) aria-current="page" @endif>
    <x-icon :name="$i['icon']" class="h-5 w-5 shrink-0 {{ $i['active'] ? 'text-brand-600 dark:text-brand-300' : 'text-slate-400 group-hover:text-slate-600' }}" />
    <span class="truncate">{{ $i['label'] }}</span>
    @if($i['icon'] === 'message' && $unread)<span class="ml-auto h-2 w-2 rounded-full bg-rose-500"></span>@endif
</a>

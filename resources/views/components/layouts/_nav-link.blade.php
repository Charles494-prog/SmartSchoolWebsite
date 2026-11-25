<a href="{{ $i['url'] }}" title="{{ $i['label'] }}" class="group flex min-h-10 items-center gap-3 rounded-xl px-3 py-2 text-sm transition {{ $i['active'] ? 'bg-brand-50 font-semibold text-brand-700 dark:bg-brand-500/15 dark:text-brand-200' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-white' }}" @if($i['active']) aria-current="page" @endif>
    <x-icon :name="$i['icon']" class="h-5 w-5 shrink-0 {{ $i['active'] ? 'text-brand-600 dark:text-brand-300' : 'text-slate-400 group-hover:text-slate-600' }}" />
    <span class="truncate" :class="mini && 'lg:hidden'">{{ $i['label'] }}</span>
    @if($i['icon'] === 'message' && $unread)<span class="ml-auto h-2 w-2 rounded-full bg-rose-500"></span>@endif
    @if(! empty($i['badge']))<span class="ml-auto flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-1.5 text-[11px] font-bold text-white" :class="mini && 'lg:hidden'" title="{{ $i['badge'] }} unread">{{ $i['badge'] > 9 ? '9+' : $i['badge'] }}</span>@endif
</a>

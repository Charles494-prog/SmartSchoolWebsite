{{-- One notification row. Unread = dot + bold title. --}}
<li class="flex items-start gap-3 px-4 py-3 {{ $nested ? 'pl-8' : '' }}" x-show="!hidden.includes(@js($n->id))"
    x-data="{ unread: {{ $n->read_at ? 'false' : 'true' }} }" x-effect="if (read[@js($n->id)] !== undefined) unread = !read[@js($n->id)]">
    <input type="checkbox" class="mt-2.5 h-5 w-5 shrink-0 accent-brand-600" aria-label="Select" :checked="selected.includes(@js($n->id))" x-on:change="toggle(@js($n->id), $el.checked)">
    @unless($nested)<span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300"><x-icon :name="$icon" class="h-5 w-5" /></span>@endunless
    <a href="{{ route('notifications.open', $n->id) }}" class="min-w-0 flex-1 rounded-lg py-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
        <span class="flex items-center gap-2"><span x-show="unread" class="h-2 w-2 shrink-0 rounded-full bg-brand-600" aria-label="Unread"></span>
            <span class="break-words" :class="unread ? 'font-semibold text-slate-900 dark:text-white' : 'text-slate-700 dark:text-slate-300'">{{ $n->data['title'] ?? 'Notice' }}</span></span>
        @if(! empty($n->data['body']))<span class="mt-0.5 block break-words text-sm text-slate-600 dark:text-slate-400">{{ $n->data['body'] }}</span>@endif
        <span class="mt-0.5 block text-xs text-slate-500">{{ $n->created_at->format('M j, Y') }} · {{ ampm($n->created_at->format('H:i')) }}</span>
    </a>
    <button type="button" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:hover:bg-white/10" :aria-label="unread ? 'Mark as read' : 'Mark as unread'" :title="unread ? 'Mark as read' : 'Mark as unread'"
            x-on:click="act(unread ? 'read' : 'unread', [@js($n->id)])">
        <x-icon name="check-circle" class="h-4 w-4" x-show="unread" /><x-icon name="bell" class="h-4 w-4" x-show="!unread" x-cloak />
    </button>
    <button type="button" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-rose-700 dark:hover:bg-white/10" aria-label="Delete" title="Delete" x-on:click="remove([@js($n->id)])"><x-icon name="x" class="h-4 w-4" /></button>
</li>

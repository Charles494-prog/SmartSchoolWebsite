@php
    $icons = ['request' => 'file', 'payment' => 'wallet', 'grade' => 'award', 'order' => 'tag', 'announcement' => 'megaphone', 'message' => 'message', 'other' => 'bell'];
@endphp
<x-layouts.app title="Notifications">
    <div x-data="noticeList(@js(route('notifications.update')), @js(csrf_token()))" x-on:pagehide.window="flush()">
        <x-page-header title="Notifications" subtitle="Only you see your notifications. Deleting one here doesn't remove it for anyone else.">
            <form method="post" action="{{ route('notifications.update') }}" x-on:submit.prevent="act('read_all')">@csrf<input type="hidden" name="action" value="read_all">
                <button class="btn min-h-11" :disabled="$store.bell.count === 0"><x-icon name="check" class="h-4 w-4" /> Mark all as seen</button></form>
            @if($readCount)
                <form method="post" action="{{ route('notifications.update') }}" x-on:submit="if (!confirm('Delete all {{ $readCount }} read notifications? This cannot be undone.')) $event.preventDefault()">@csrf<input type="hidden" name="action" value="clear_read">
                    <button class="btn min-h-11"><x-icon name="trash" class="h-4 w-4" /> Clear read</button></form>
            @endif
        </x-page-header>

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <nav class="inline-flex flex-wrap rounded-xl bg-slate-100 p-1 text-sm font-medium dark:bg-white/5" aria-label="Filter">
                @foreach(\App\Support\NoticeFeed::FILTERS as $k => $label)
                    <a href="{{ route('notifications', $k === 'all' ? [] : ['filter' => $k]) }}" @if($filter === $k) aria-current="page" @endif
                       class="flex min-h-10 items-center gap-1.5 rounded-lg px-3.5 {{ $filter === $k ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-800 dark:text-white' : 'text-slate-600 hover:text-slate-900 dark:text-slate-300' }}">{{ $label }}@if($k === 'unread')<span class="text-xs opacity-70" x-text="$store.bell.count">{{ $unread }}</span>@endif</a>
                @endforeach
            </nav>
            <div class="ml-auto flex items-center gap-2" x-show="selected.length" x-cloak>
                <span class="text-sm text-slate-600 dark:text-slate-300" x-text="selected.length + ' selected'"></span>
                <button type="button" class="btn min-h-11" x-on:click="act('read', selected)">Mark read</button>
                <button type="button" class="btn min-h-11 text-rose-700" x-on:click="if (confirm('Delete ' + selected.length + ' notifications?')) removeMany(selected)"><x-icon name="trash" class="h-4 w-4" /> Delete selected</button>
                <button type="button" class="btn min-h-11" x-on:click="selected = []">Cancel</button>
            </div>
        </div>

        @if($list->isEmpty())
            <div class="card"><x-empty icon="bell" :title="$filter === 'all' ? 'You’re all caught up' : 'Nothing here'" :text="$filter === 'all' ? 'New notifications will show up here.' : 'No '.strtolower(\App\Support\NoticeFeed::FILTERS[$filter]).' notifications.'" /></div>
        @else
            <div class="space-y-6">
                @foreach($groups as $bucket => $rows)
                    <section>
                        <h2 class="mb-2 px-1 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $bucket }}</h2>
                        <ul class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
                            @foreach($rows as $g)
                                @php $ids = $g['items']->pluck('id')->all(); $anyUnread = $g['items']->contains(fn ($n) => ! $n->read_at); @endphp
                                @if($g['summary'])
                                    <li x-data="{ open: false }" x-show="!hidden.some((h) => @js($ids).includes(h))">
                                        <div class="flex items-center gap-3 px-4 py-3">
                                            <input type="checkbox" class="h-5 w-5 shrink-0 accent-brand-600" aria-label="Select group" x-on:change="toggleMany(@js($ids), $el.checked)">
                                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300"><x-icon :name="$icons[$g['type']]" class="h-5 w-5" /></span>
                                            <button type="button" class="min-w-0 flex-1 text-left" x-on:click="open = !open" :aria-expanded="open">
                                                <span class="flex items-center gap-2">@if($anyUnread)<span class="h-2 w-2 shrink-0 rounded-full bg-brand-600" aria-label="Unread"></span>@endif
                                                    <span class="break-words {{ $anyUnread ? 'font-semibold' : '' }}">{{ $g['summary'] }}</span></span>
                                                <span class="text-xs text-slate-500">{{ $g['items']->first()->created_at->diffForHumans() }} · <span x-text="open ? 'Hide' : 'Show all {{ count($ids) }}'"></span></span>
                                            </button>
                                            <button type="button" class="flex h-11 w-11 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-rose-700 dark:hover:bg-white/10" aria-label="Delete these {{ count($ids) }}" x-on:click="remove(@js($ids))"><x-icon name="x" class="h-4 w-4" /></button>
                                        </div>
                                        <ul x-show="open" x-cloak class="border-t border-slate-100 bg-slate-50/60 dark:border-slate-800 dark:bg-white/5">
                                            @foreach($g['items'] as $n)
                                                @include('messages._notice', ['n' => $n, 'icon' => $icons[$g['type']], 'nested' => true])
                                            @endforeach
                                        </ul>
                                    </li>
                                @else
                                    @include('messages._notice', ['n' => $g['items']->first(), 'icon' => $icons[$g['type']], 'nested' => false])
                                @endif
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>
            <div class="mt-4">{{ $list->links() }}</div>
        @endif

        {{-- Undo toast --}}
        <div x-show="pending.length" x-cloak x-transition class="fixed inset-x-4 bottom-4 z-50 mx-auto flex max-w-md items-center gap-3 rounded-2xl bg-slate-900 px-4 py-3 text-sm text-white shadow-2xl" role="status" aria-live="polite">
            <span class="flex-1" x-text="pendingCount + (pendingCount === 1 ? ' notification deleted' : ' notifications deleted')"></span>
            <button type="button" class="min-h-11 rounded-lg px-3 font-semibold text-brand-200 hover:bg-white/10" x-on:click="undo()">Undo</button>
        </div>
        <p x-show="error" x-cloak x-text="error" class="fixed inset-x-4 bottom-20 z-50 mx-auto max-w-md rounded-xl bg-rose-700 px-4 py-2 text-sm text-white" role="alert"></p>
    </div>

    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        document.addEventListener('alpine:init', () => {
            Alpine.data('noticeList', (url, token) => ({
                selected: [], hidden: [], pending: [], timer: null, error: '', read: {},
                get pendingCount() { return this.pending.flat().length },
                toggle(id, on) { on ? this.selected.push(id) : this.selected = this.selected.filter((x) => x !== id) },
                toggleMany(ids, on) { ids.forEach((id) => { this.selected = this.selected.filter((x) => x !== id); if (on) this.selected.push(id) }) },
                async send(action, ids = []) {
                    this.error = '';
                    const body = new FormData(); body.append('_token', token); body.append('action', action); ids.forEach((i) => body.append('ids[]', i));
                    try {
                        const r = await fetch(url, { method: 'POST', body, headers: { Accept: 'application/json' } });
                        if (!r.ok) throw new Error();
                        const j = await r.json(); Alpine.store('bell').count = j.unread; return true;
                    } catch (e) { this.error = 'Could not save that. Check your connection and try again.'; return false }
                },
                async act(action, ids = []) {
                    if (await this.send(action, ids)) {
                        if (action === 'read_all') { location.reload(); return }
                        ids.forEach((id) => this.read[id] = action === 'read'); this.selected = [];
                        if (action === 'read' && ids.length > 1) location.reload();
                    }
                },
                // Delete waits 6 s so it can be undone; leaving the page sends it right away.
                remove(ids) { this.hidden.push(...ids); this.pending.push(ids); this.selected = this.selected.filter((x) => !ids.includes(x)); clearTimeout(this.timer); this.timer = setTimeout(() => this.flush(), 6000) },
                removeMany(ids) { this.remove([...ids]) },
                undo() { clearTimeout(this.timer); const back = this.pending.flat(); this.hidden = this.hidden.filter((h) => !back.includes(h)); this.pending = [] },
                flush() {
                    const ids = this.pending.flat(); if (!ids.length) return; this.pending = []; clearTimeout(this.timer);
                    const body = new FormData(); body.append('_token', token); body.append('action', 'delete'); ids.forEach((i) => body.append('ids[]', i));
                    fetch(url, { method: 'POST', body, headers: { Accept: 'application/json' }, keepalive: true }).then((r) => r.json()).then((j) => Alpine.store('bell').count = j.unread).catch(() => { this.error = 'Could not delete. Reload and try again.'; this.hidden = this.hidden.filter((h) => !ids.includes(h)) });
                },
            }));
        });
    </script>
</x-layouts.app>

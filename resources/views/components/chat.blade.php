@props(['mode' => 'floating'])
@php $full = $mode === 'page'; @endphp
<div x-data="chatBox({{ $full ? 'true' : 'false' }})" x-init="init()" @open-chat.window="openWith($event.detail)"
     class="{{ $full ? 'h-[calc(100vh-12rem)] min-h-[28rem]' : 'fixed right-4 bottom-4 z-40 lg:right-6 lg:bottom-6' }}">

    @unless($full)
        {{-- Floating bubble --}}
        <button x-show="!open" @click="toggle()" class="relative flex h-14 w-14 items-center justify-center rounded-full bg-brand-600 text-white shadow-lg shadow-brand-600/30 transition hover:scale-105 hover:bg-brand-700 {{ auth()->user()->hasRole('student', 'parent') ? 'mb-16 lg:mb-0' : '' }}" aria-label="Open chat">
            <x-icon name="message" class="h-6 w-6" />
            <span x-show="unread" x-cloak class="absolute -top-1 -right-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-1 text-[11px] font-bold ring-2 ring-white" x-text="unread"></span>
        </button>
    @endunless

    <div x-show="open" x-cloak x-transition.origin.bottom.right
         class="{{ $full ? 'flex h-full' : 'flex h-[min(36rem,calc(100vh-6rem))] w-[min(24rem,calc(100vw-2rem))] flex-col' }} overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-900/10 dark:bg-slate-900 dark:ring-white/10">

        {{-- Conversation list --}}
        <div x-show="{{ $full ? 'true' : '!current' }}" class="{{ $full ? 'w-80 shrink-0 border-r border-slate-100 dark:border-white/10' : 'flex-1' }} flex min-h-0 flex-col">
            <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-3 dark:border-white/10">
                <div class="flex-1 text-base font-bold">Chats</div>
                <button @click="picking = !picking; if (picking) loadContacts()" class="rounded-lg p-1.5 text-brand-600 hover:bg-brand-50" title="New chat"><x-icon name="plus" class="h-5 w-5" /></button>
                @unless($full)<button @click="open=false" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100" title="Close"><x-icon name="x" class="h-5 w-5" /></button>@endunless
            </div>
            <div x-show="picking" class="border-b border-slate-100 p-3 dark:border-white/10">
                <input x-model="q" @input.debounce.250ms="loadContacts()" class="input" placeholder="Search people you can message…">
                <div class="mt-2 max-h-56 overflow-y-auto">
                    <template x-for="c in contacts" :key="c.id">
                        <button @click="startWith(c)" class="flex w-full items-center gap-3 rounded-xl p-2 text-left hover:bg-slate-50 dark:hover:bg-white/5">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-700" x-text="c.initials"></span>
                            <span class="min-w-0"><span class="block truncate text-sm font-semibold" x-text="c.name"></span><span class="block text-xs text-slate-500" x-text="c.role"></span></span>
                        </button>
                    </template>
                    <p x-show="!contacts.length" class="p-2 text-sm text-slate-500">No one found.</p>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto p-2">
                <template x-for="c in list" :key="c.id">
                    <button @click="select(c)" :class="current && current.id === c.id ? 'bg-brand-50 dark:bg-brand-500/15' : 'hover:bg-slate-50 dark:hover:bg-white/5'" class="flex w-full items-center gap-3 rounded-xl p-2.5 text-left">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-600 text-sm font-bold text-white" x-text="c.with.initials"></span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-2"><span class="truncate text-sm" :class="c.unread ? 'font-bold' : 'font-semibold'" x-text="c.with.name"></span><span class="ml-auto shrink-0 text-[11px] text-slate-400" x-text="c.time"></span></span>
                            <span class="block truncate text-xs" :class="c.unread ? 'font-semibold text-slate-800 dark:text-slate-100' : 'text-slate-500'" x-text="c.last || c.with.role"></span>
                        </span>
                        <span x-show="c.unread" class="h-2.5 w-2.5 shrink-0 rounded-full bg-brand-600"></span>
                    </button>
                </template>
                <div x-show="!list.length && !picking" class="px-4 py-10 text-center text-sm text-slate-500">
                    No chats yet.<br><button @click="picking=true; loadContacts()" class="link mt-2">Start a chat</button>
                </div>
            </div>
        </div>

        {{-- Open conversation --}}
        <div x-show="current" class="flex min-h-0 flex-1 flex-col">
            <template x-if="current">
                <div class="flex items-center gap-3 border-b border-slate-100 px-3 py-2.5 dark:border-white/10">
                    @unless($full)<button @click="current=null; refreshList()" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100" title="Back"><x-icon name="chevron-left" class="h-5 w-5" /></button>@endunless
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-600 text-xs font-bold text-white" x-text="current.with.initials"></span>
                    <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold" x-text="current.with.name"></span><span class="block text-xs text-slate-500" x-text="current.with.role"></span></span>
                    @unless($full)<button @click="open=false" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100" title="Close"><x-icon name="x" class="h-5 w-5" /></button>@endunless
                </div>
            </template>
            <div x-ref="scroll" class="flex-1 space-y-1.5 overflow-y-auto bg-slate-50/60 p-3 dark:bg-white/[.02]">
                <template x-for="m in msgs" :key="m.id">
                    <div class="flex" :class="m.mine ? 'justify-end' : 'justify-start'">
                        <div class="max-w-[78%] rounded-2xl px-3.5 py-2 text-sm whitespace-pre-line" :title="m.time"
                             :class="m.mine ? 'rounded-br-md bg-brand-600 text-white' : 'rounded-bl-md bg-white text-slate-800 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-100 dark:ring-white/10'" x-text="m.body"></div>
                    </div>
                </template>
                <p x-show="current && !msgs.length" class="py-8 text-center text-sm text-slate-500">Say hello to start the conversation.</p>
            </div>
            <form @submit.prevent="send()" class="flex items-end gap-2 border-t border-slate-100 p-2.5 dark:border-white/10">
                <textarea x-model="draft" @keydown.enter.prevent="if (!$event.shiftKey) send(); else draft += '\n'" rows="1" class="input max-h-28 resize-none" placeholder="Type a message…"></textarea>
                <button class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-600 text-white hover:bg-brand-700 disabled:opacity-40" :disabled="!draft.trim() || sending" aria-label="Send">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                </button>
            </form>
        </div>
        @if($full)
            <div x-show="!current" class="hidden flex-1 items-center justify-center text-center text-sm text-slate-500 md:flex">
                <div><div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 text-brand-500"><x-icon name="message" class="h-7 w-7" /></div>Pick a chat on the left, or press + to start one.</div>
            </div>
        @endif
    </div>
</div>

@once
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    function chatBox(full) {
        const csrf = '{{ csrf_token() }}';
        const json = (url, opt = {}) => fetch(url, { headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf }, ...opt }).then(r => r.ok ? r.json() : Promise.reject(r));
        return {
            open: full, list: [], unread: 0, current: null, msgs: [], draft: '', sending: false, picking: false, contacts: [], q: '', timer: null,
            init() { this.refreshList(); this.n = 0; this.timer = setInterval(() => this.tick(), 2000); document.addEventListener('visibilitychange', () => { if (!document.hidden) this.tick(true) }) },
            toggle() { this.open = !this.open; if (this.open) this.refreshList() },
            async tick(force) {
                if (document.hidden && !force) return;
                this.n++;
                if (this.open && this.current) this.loadMsgs(false);
                if (force || this.n % (this.open ? 2 : 4) === 0) this.refreshList();
            },
            async refreshList() { try { const d = await json('{{ route('chat.index') }}'); this.list = d.conversations; this.unread = d.unread } catch (e) {} },
            async loadContacts() { this.contacts = await json('{{ route('chat.contacts') }}?q=' + encodeURIComponent(this.q)) },
            async startWith(c) {
                const d = await json('{{ route('chat.start') }}', { method: 'POST', body: JSON.stringify({ to: c.id }) });
                this.picking = false; this.q = ''; this.select({ id: d.id, with: d.with }); this.refreshList();
            },
            openWith(detail) { this.open = true; if (detail && detail.to) this.startWith({ id: detail.to }) },
            select(c) { this.current = c; this.msgs = []; this.loadMsgs(true) },
            async loadMsgs(scroll) {
                const after = this.msgs.length ? this.msgs[this.msgs.length - 1].id : 0;
                const id = this.current.id;
                const fresh = await json('{{ url('/chat') }}/' + id + '?after=' + after);
                if (!this.current || this.current.id !== id) return;
                if (fresh.length) { this.msgs.push(...fresh); scroll = true }
                if (scroll) this.$nextTick(() => this.$refs.scroll.scrollTop = this.$refs.scroll.scrollHeight);
                const c = this.list.find(x => x.id === id); if (c && c.unread) { c.unread = false; this.unread = Math.max(0, this.unread - 1) }
            },
            async send() {
                const body = this.draft.trim(); if (!body || this.sending) return;
                this.sending = true;
                try {
                    const m = await json('{{ url('/chat') }}/' + this.current.id, { method: 'POST', body: JSON.stringify({ body }) });
                    this.msgs.push(m); this.draft = '';
                    this.$nextTick(() => this.$refs.scroll.scrollTop = this.$refs.scroll.scrollHeight);
                    this.refreshList();
                } catch (e) { alert('Message not sent. Please try again.') }
                this.sending = false;
            },
        }
    }
</script>
@endonce

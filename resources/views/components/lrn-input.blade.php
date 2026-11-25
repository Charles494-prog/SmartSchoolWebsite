@props(['name' => 'lrn', 'value' => null, 'ignore' => null, 'label' => 'LRN (Learner Reference Number)', 'compact' => false])
{{-- 12 boxes that behave like one field (paste fills them all), with a live "already used" check. --}}
<div x-data="lrnInput(@js((string) $value), @js($ignore))" class="{{ $compact ? '' : 'sm:col-span-2' }}">
    @unless($compact)<span class="label">{{ $label }}</span>@endunless
    <input type="hidden" name="{{ $name }}" :value="value">
    <div class="flex flex-wrap items-center gap-1" x-on:paste.prevent="paste($event)">
        <template x-for="(d, i) in digits" :key="i">
            <input type="text" inputmode="numeric" maxlength="1" autocomplete="off" :aria-label="'LRN digit ' + (i + 1)"
                   class="h-11 w-9 rounded-lg border text-center font-mono text-lg font-semibold focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:bg-slate-900"
                   :class="[(i === 3 || i === 6 || i === 9) ? 'ml-2' : '', taken ? 'border-rose-400 bg-rose-50 dark:bg-rose-500/10' : (value.length === 12 && checked ? 'border-emerald-400' : 'border-slate-300 dark:border-slate-700')]"
                   :value="d" x-ref="'d' + i" :data-i="i"
                   x-on:input="type(i, $event)" x-on:keydown.backspace="back(i, $event)" x-on:keydown.arrow-left.prevent="focus(i - 1)" x-on:keydown.arrow-right.prevent="focus(i + 1)" x-on:focus="$event.target.select()">
        </template>
        <button type="button" x-show="value" class="ml-2 text-xs text-slate-400 hover:text-slate-700" x-on:click="set('')">Clear</button>
    </div>
    <p class="mt-1 text-xs" :class="taken ? 'font-semibold text-rose-600' : 'text-slate-500'">
        <span x-show="taken" x-text="'Already used by ' + taken + ' – every LRN must be unique.'"></span>
        <span x-show="!taken && value.length > 0 && value.length < 12" x-text="(12 - value.length) + ' more digit(s)'"></span>
        <span x-show="!taken && value.length === 12 && checked" class="text-emerald-600">✓ Available</span>
        <span x-show="!value">12 digits from DepEd. Paste it into any box. Leave empty if the student has none yet.</span>
    </p>
    @error($name)<p class="field-error">{{ $message }}</p>@enderror
</div>
@once
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        function lrnInput(initial, ignore) {
            return {
                digits: Array.from({ length: 12 }, (_, i) => (initial || '')[i] || ''), taken: null, checked: false, t: null,
                get value() { return this.digits.join('') },
                boxes() { return [...this.$el.querySelectorAll('input[data-i]')] },
                focus(i) { const b = this.boxes()[Math.max(0, Math.min(11, i))]; b && b.focus() },
                set(v) { v = (v || '').replace(/\D/g, '').slice(0, 12); this.digits = Array.from({ length: 12 }, (_, i) => v[i] || ''); this.check(); },
                type(i, e) {
                    const v = e.target.value.replace(/\D/g, '');
                    if (v.length > 1) { this.set(this.value.slice(0, i) + v); this.focus(i + v.length); return }
                    this.digits[i] = v; e.target.value = v; if (v) this.focus(i + 1); this.check();
                },
                back(i, e) { if (!this.digits[i] && i > 0) { e.preventDefault(); this.digits[i - 1] = ''; this.focus(i - 1); this.check() } },
                paste(e) { const v = (e.clipboardData || window.clipboardData).getData('text'); this.set(v); this.focus(Math.min(11, v.replace(/\D/g, '').length)) },
                check() {
                    this.taken = null; this.checked = false; clearTimeout(this.t);
                    if (this.value.length !== 12) return;
                    this.t = setTimeout(async () => {
                        try { const r = await (await fetch('{{ route('students.lrn-check') }}?lrn=' + this.value + (ignore ? '&ignore=' + ignore : ''), { headers: { Accept: 'application/json' } })).json(); this.taken = r.taken; this.checked = true } catch (e) {}
                    }, 250);
                },
                init() { if (this.value.length === 12) this.check() },
            };
        }
    </script>
@endonce

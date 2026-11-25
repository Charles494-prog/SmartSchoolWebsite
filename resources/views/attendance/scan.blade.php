<x-layouts.app :title="$gate ? 'Gate scanner' : 'QR scanner'">
    <div x-data="scanner()" x-init="init()" x-on:fullscreenchange.document="if (!document.fullscreenElement) full = false" x-on:keydown.escape.window="full && exitFull()"
         :class="full ? 'fixed inset-0 z-[70] overflow-y-auto bg-slate-100 p-4 sm:p-8 dark:bg-slate-950' : ''" class="transition-colors">
      <div class="mb-4 flex items-center justify-end gap-2" :class="full && 'mx-auto max-w-7xl'">
          <span x-show="full" x-cloak class="mr-auto text-lg font-semibold text-slate-900">{{ \App\Models\Setting::get('school_name') }} · {{ $gate ? 'Gate' : 'Attendance' }}</span>
          <button type="button" class="btn min-h-11" x-on:click="full ? exitFull() : enterFull()" :aria-pressed="full">
              <span x-text="full ? 'Exit full screen' : 'Full screen'">Full screen</span>
          </button>
      </div>
      <div class="grid gap-4 lg:grid-cols-2" :class="full && 'mx-auto max-w-7xl lg:grid-cols-[2fr_3fr]'">
        <div class="card">
            @if($gate)
                <h1 class="h2">Gate scanner</h1>
                <p class="mt-1 text-sm text-slate-500">First scan of the day = <b>IN</b> (late after {{ ampm($lateAfter) }}). Next scan when leaving = <b>OUT</b>. Parents are notified when a student is late.</p>
                <div class="mt-4"></div>
            @else
                <h1 class="h2">QR scanner</h1>
                <div class="mt-4 mb-4">
                    <label class="label">{{ $canGate ? 'Where are you scanning?' : 'Which class are you teaching now?' }}</label>
                    <select x-model="classId" class="input">
                        @if($canGate)<option value="">School gate – in and out (late after {{ ampm($lateAfter) }})</option>@endif
                        @foreach($classes as $id => $label)<option value="{{ $id }}">{{ $canGate ? 'Class: ' : '' }}{{ $label }}</option>@endforeach
                    </select>
                    @if(! $canGate && $classes->isEmpty())<p class="field-error">You have no classes assigned yet.</p>@endif
                    <p class="mt-1.5 text-xs text-slate-500" x-text="classId ? 'Records who attended this subject today. Late = 15 minutes after the class starts.' : 'First scan = IN, next scan = OUT.'"></p>
                </div>
            @endif
            <p class="muted mb-3">Scan the student's ID card with a USB QR scanner (it types into the box) or this device's camera.</p>
            <form @submit.prevent="send(code)" class="flex gap-2">
                <input x-ref="box" x-model="code" class="input text-lg" placeholder="Scan or type student no./LRN…" autofocus>
                <button class="btn btn-primary">Scan</button>
            </form>
            <div class="mt-4 flex gap-2">
                <button class="btn" @click="camera ? stopCam() : startCam()" x-text="camera ? 'Stop camera' : 'Use camera'"></button>
            </div>
            <div id="qr-reader" class="mt-3 overflow-hidden rounded-lg" x-show="camera"></div>
        </div>
        <div class="space-y-3">
            <div :class="full ? 'min-h-[50vh]' : 'min-h-48'" class="card flex flex-col items-center justify-center text-center transition"
                 :class="{ 'bg-green-600 text-white': last && last.status==='present', 'bg-slate-800 text-white': last && last.status==='out', 'bg-amber-500 text-white': last && last.status==='late', 'bg-blue-600 text-white': last && last.status==='already', 'bg-rose-600 text-white': last && !last.ok }">
                <template x-if="!last"><p class="muted">Waiting for a scan…</p></template>
                <template x-if="last">
                    <div>
                        <div :class="full ? 'text-5xl sm:text-6xl' : 'text-3xl'" class="font-bold" x-text="last.name || 'Unknown'"></div>
                        <div :class="full ? 'mt-3 text-2xl' : 'mt-1 text-lg'" x-text="last.message"></div>
                        <div class="text-sm opacity-90" x-text="last.section || ''"></div>
                    </div>
                </template>
            </div>
            <div class="card">
                <h2 class="h2 mb-2">Recent</h2>
                <ul class="space-y-1 text-sm"><template x-for="r in recent"><li><span x-text="r.time"></span> · <b x-text="r.name || '?'"></b> – <span x-text="r.message"></span></li></template></ul>
            </div>
        </div>
      </div>
    </div>
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        function scanner() {
            return {
                full: false,
                async enterFull() { this.full = true; try { await document.documentElement.requestFullscreen?.() } catch (e) {} this.$nextTick(() => this.$refs.box.focus()) },
                async exitFull() { this.full = false; if (document.fullscreenElement) { try { await document.exitFullscreen() } catch (e) {} } },
                code: '', classId: '{{ $selected ?: '' }}', last: null, recent: [], camera: false, cam: null, busy: false, lastCode: '', lastAt: 0,
                init() { setInterval(() => { if (!this.camera && document.activeElement !== this.$refs.box) this.$refs.box.focus() }, 1500) },
                beep(ok) { try { const a = new AudioContext(), o = a.createOscillator(); o.frequency.value = ok ? 880 : 220; o.connect(a.destination); o.start(); o.stop(a.currentTime + 0.15) } catch (e) {} },
                async send(code) {
                    code = (code || '').trim(); if (!code || this.busy) return;
                    if (code === this.lastCode && Date.now() - this.lastAt < 4000) return;
                    this.lastCode = code; this.lastAt = Date.now(); this.busy = true;
                    try {
                        const r = await fetch('{{ route('attendance.checkin') }}', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: JSON.stringify({ code, class_id: this.classId || null }) });
                        this.last = await r.json();
                    } catch (e) { this.last = { ok: false, message: 'Connection problem – try again.' } }
                    this.beep(this.last.ok); this.recent.unshift({ ...this.last, time: new Date().toLocaleTimeString() }); this.recent = this.recent.slice(0, 15);
                    this.code = ''; this.busy = false;
                },
                async startCam() {
                    this.camera = true; await this.$nextTick();
                    this.cam = new Html5Qrcode('qr-reader');
                    try { await this.cam.start({ facingMode: 'environment' }, { fps: 10, qrbox: 230 }, (t) => this.send(t)) }
                    catch (e) { this.camera = false; alert('Camera not available: ' + e) }
                },
                async stopCam() { if (this.cam) { await this.cam.stop(); } this.camera = false }
            }
        }
    </script>
</x-layouts.app>

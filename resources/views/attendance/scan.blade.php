<x-layouts.app :title="$gate ? 'Gate scanner' : 'QR scanner'">
    <div x-data="scanner()" x-init="init()" x-on:fullscreenchange.document="if (!document.fullscreenElement) full = false" x-on:keydown.escape.window="camOpen ? closeCam() : (full && exitFull())"
         :class="full ? 'fixed inset-0 z-[70] overflow-y-auto bg-slate-100 p-4 sm:p-6 dark:bg-slate-950' : ''">

      {{-- 1. Header: what this page is + settings, all on one quiet line --}}
      <div class="mb-4 flex flex-wrap items-end gap-3">
          <div class="mr-auto">
              <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $gate ? 'Gate scanner' : 'QR scanner' }}</h1>
              <p class="text-sm text-slate-500" x-text="classId ? 'Class attendance – one scan per student' : 'School gate – first scan IN, next scan OUT'"></p>
          </div>
          @unless($gate)
              <label class="block">
                  <span class="mb-1 block text-xs font-medium text-slate-500">{{ $canGate ? 'Scanning at' : 'Class' }}</span>
                  <select x-model="classId" class="input h-11 min-w-56 py-0 text-sm">
                      @if($canGate)<option value="">School gate (late after {{ ampm($lateAfter) }})</option>@endif
                      @foreach($classes as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                  </select>
              </label>
          @endunless
          <label class="block" x-show="cameras.length" x-cloak>
              <span class="mb-1 block text-xs font-medium text-slate-500">Camera</span>
              <select class="input h-11 max-w-56 py-0 text-sm" x-model.number="camIndex" x-on:change="pickCam()">
                  <template x-for="(c, i) in cameras" :key="c.id"><option :value="i" :selected="i === camIndex" x-text="c.label || ('Camera ' + (i + 1))"></option></template>
              </select>
          </label>
          <button type="button" class="btn h-11" x-on:click="full ? exitFull() : enterFull()" :aria-pressed="full" x-text="full ? 'Exit full screen' : 'Full screen'">Full screen</button>
      </div>
      @if(! $gate && ! $canGate && $classes->isEmpty())<p class="field-error mb-3">You have no classes assigned yet.</p>@endif

      <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_400px]">

        {{-- 2. Scanner (left): camera, or a start screen --}}
        <div class="space-y-3">
            <div x-ref="camWrap" class="relative flex h-[calc(100dvh-14rem)] min-h-[380px] flex-col overflow-hidden rounded-2xl bg-slate-900 text-white shadow-lg" role="region" aria-label="Scanner">
                {{-- Start screen --}}
                <div x-show="!camOpen" class="flex flex-1 flex-col items-center justify-center gap-5 p-6 text-center">
                    <div class="flex h-20 w-20 items-center justify-center rounded-3xl bg-white/10"><x-icon name="qr" class="h-10 w-10" /></div>
                    <div>
                        <p class="text-xl font-semibold">Ready to scan</p>
                        <p class="mt-1 text-sm text-white/70">Use the camera, or just scan with a USB QR scanner – no click needed.</p>
                    </div>
                    <button type="button" class="btn btn-primary min-h-12 px-6 text-base" x-on:click="openCam()"><x-icon name="qr" class="h-5 w-5" /> Start camera</button>
                </div>

                {{-- Camera --}}
                <div x-show="camOpen" x-cloak class="relative flex-1 overflow-hidden bg-black">
                    <div id="qr-reader" class="absolute inset-0"></div>
                    <div x-show="camera" class="pointer-events-none absolute inset-0 flex items-center justify-center">
                        <div class="relative aspect-square w-[min(60%,55vh)] rounded-3xl shadow-[0_0_0_9999px_rgba(0,0,0,.45)]">
                            <span class="absolute -top-1 -left-1 h-10 w-10 rounded-tl-3xl border-t-4 border-l-4 border-white"></span>
                            <span class="absolute -top-1 -right-1 h-10 w-10 rounded-tr-3xl border-t-4 border-r-4 border-white"></span>
                            <span class="absolute -bottom-1 -left-1 h-10 w-10 rounded-bl-3xl border-b-4 border-l-4 border-white"></span>
                            <span class="absolute -right-1 -bottom-1 h-10 w-10 rounded-br-3xl border-r-4 border-b-4 border-white"></span>
                            <span class="scanline absolute inset-x-6 h-0.5 rounded-full bg-brand-500/90 shadow-[0_0_12px_2px_rgba(99,102,241,.8)]"></span>
                        </div>
                    </div>
                    <div class="absolute top-3 right-3 flex gap-2">
                        <button type="button" class="flex h-11 w-11 items-center justify-center rounded-full bg-black/50 backdrop-blur hover:bg-black/70" x-show="cameras.length > 1" x-on:click="switchCam()" aria-label="Switch camera"><x-icon name="repeat" class="h-5 w-5" /></button>
                        <button type="button" class="flex h-11 items-center gap-2 rounded-full bg-black/50 px-4 text-sm font-medium backdrop-blur hover:bg-black/70" x-on:click="closeCam()" aria-label="Stop camera"><x-icon name="x" class="h-5 w-5" /> Stop</button>
                    </div>
                    <div x-show="!camera && !camError" class="absolute inset-0 flex flex-col items-center justify-center gap-3 text-white/80">
                        <span class="h-8 w-8 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                        <span class="text-sm">Starting camera…</span>
                    </div>
                    <div x-show="camError" class="absolute inset-0 flex items-center justify-center p-4">
                        <div role="alert" class="max-h-[80%] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-5 text-sm text-slate-700 shadow-2xl dark:bg-slate-900 dark:text-slate-200">
                <template x-if="camError === 'insecure'">
                    <div>
                        <p class="font-semibold">The camera is blocked on this address</p>
                        <p class="mt-1">Browsers only allow the camera on secure (https://) addresses or on the server computer itself. You opened <b x-text="location.origin"></b>.</p>
                        <p class="mt-2 font-semibold">Ways to fix it</p>
                        <ol class="mt-1 list-decimal space-y-1 pl-5">
                            <li><b>Use a USB QR scanner</b> – it works on any address, no camera needed. Just scan into the box above.</li>
                            <li><b>On the server computer</b>, open <code>http://localhost:8000/attendance/scan</code> instead – the camera is allowed there.</li>
                            <li><b>On this device (Chrome, Brave, Edge):</b> open <code>chrome://flags/#unsafely-treat-insecure-origin-as-secure</code> (Brave: <code>brave://flags/…</code>), turn it on, type <b x-text="location.origin"></b> in the box, click <b>Relaunch</b>, then try again.</li>
                            <li><b>When SmartSchool is Online</b> (on a web host with HTTPS) the camera works everywhere.</li>
                        </ol>
                    </div>
                </template>
                <p x-show="camError === 'denied'">Camera permission was refused. Click the camera icon in the address bar, choose <b>Allow</b>, then press <b>Use camera</b> again.</p>
                <p x-show="camError === 'none'">No camera was found on this device. Plug in a webcam or use a USB QR scanner.</p>
                <p x-show="camError === 'other'">The camera could not start. Close other apps using the camera and try again, or use a USB QR scanner.</p>
                        </div>
                    </div>
                    <p x-show="camera" class="absolute inset-x-0 bottom-4 text-center text-sm text-white/80">Hold the ID card's QR code inside the frame</p>
                </div>

                {{-- Big flash over the scanner after each scan --}}
                <div x-show="last && flash" x-cloak x-transition.opacity.duration.200ms class="pointer-events-none absolute inset-x-0 bottom-0 p-4">
                    <div class="mx-auto flex max-w-lg items-center gap-4 rounded-2xl px-5 py-4 text-white shadow-2xl" :class="tone(last).solid">
                        <x-icon name="check-circle" class="h-9 w-9 shrink-0" x-show="last && last.ok" />
                        <x-icon name="alert" class="h-9 w-9 shrink-0" x-show="last && !last.ok" />
                        <div class="min-w-0">
                            <div class="truncate text-2xl font-bold" x-text="last ? (last.name || 'Unknown card') : ''"></div>
                            <div class="text-base font-medium opacity-95" x-text="last ? tone(last).label : ''"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- USB / manual entry: small, always ready --}}
            <form @submit.prevent="send(code)" class="flex items-center gap-2">
                <label for="scan-box" class="sr-only">Student no. or LRN</label>
                <input id="scan-box" x-ref="box" x-model="code" class="input h-11 flex-1" placeholder="USB scanner types here – or type student no./LRN and press Enter" autocomplete="off" autofocus>
                <button class="btn h-11">Enter</button>
            </form>
        </div>

        {{-- 3. Results (right): what just happened, then the list --}}
        <aside class="space-y-4 lg:sticky lg:top-24" aria-live="polite">
            {{-- Latest result: the most important thing on the page --}}
            <div class="overflow-hidden rounded-2xl border shadow-sm transition-colors" :class="last ? tone(last).card : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900'">
                <template x-if="!last">
                    <div class="flex min-h-40 flex-col items-center justify-center p-6 text-center">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Latest scan</p>
                        <p class="mt-2 text-lg font-medium text-slate-500">Waiting for the first scan…</p>
                    </div>
                </template>
                <template x-if="last">
                    <div class="p-6">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-bold" :class="tone(last).pill">
                                <span class="h-2 w-2 rounded-full bg-current"></span><span x-text="tone(last).label"></span>
                            </span>
                            <span class="ml-auto text-sm text-slate-500" x-text="recent[0]?.time"></span>
                        </div>
                        <div class="mt-3 text-3xl font-bold leading-tight text-slate-900 dark:text-white" x-text="last.name || 'Unknown card'"></div>
                        <div class="mt-1 text-slate-600 dark:text-slate-300" x-text="last.section || ''"></div>
                        <div class="mt-3 text-sm text-slate-600 dark:text-slate-300" x-text="last.message"></div>
                    </div>
                </template>
            </div>

            {{-- Today's tally --}}
            <div class="grid grid-cols-4 gap-2 text-center">
                <div class="rounded-xl bg-white p-3 shadow-sm dark:bg-slate-900"><div class="text-2xl font-bold text-emerald-600" x-text="counts.in">0</div><div class="text-xs text-slate-500">In</div></div>
                <div class="rounded-xl bg-white p-3 shadow-sm dark:bg-slate-900"><div class="text-2xl font-bold text-amber-600" x-text="counts.late">0</div><div class="text-xs text-slate-500">Late</div></div>
                <div class="rounded-xl bg-white p-3 shadow-sm dark:bg-slate-900"><div class="text-2xl font-bold text-slate-700 dark:text-slate-200" x-text="counts.out">0</div><div class="text-xs text-slate-500">Out</div></div>
                <div class="rounded-xl bg-white p-3 shadow-sm dark:bg-slate-900"><div class="text-2xl font-bold text-rose-600" x-text="counts.err">0</div><div class="text-xs text-slate-500">Problems</div></div>
            </div>

            {{-- Recent scans --}}
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3 dark:border-slate-800">
                    <h2 class="font-semibold text-slate-900 dark:text-white">Recent scans</h2>
                    <span class="text-xs text-slate-500" x-show="recent.length" x-text="'Last ' + recent.length"></span>
                </div>
                <p x-show="!recent.length" class="px-5 py-6 text-center text-sm text-slate-500">Scans will appear here.</p>
                <ul class="max-h-[40vh] divide-y divide-slate-100 overflow-y-auto dark:divide-slate-800">
                    <template x-for="(r, i) in recent" :key="r.key">
                        <li class="flex items-center gap-3 px-5 py-3" :class="i === 0 && 'bg-slate-50 dark:bg-slate-800/50'">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold" :class="tone(r).pill" x-text="initials(r.name)"></span>
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-medium text-slate-900 dark:text-white" x-text="r.name || 'Unknown card'"></div>
                                <div class="truncate text-xs text-slate-500" x-text="r.time + (r.section ? ' · ' + r.section : '')"></div>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-bold" :class="tone(r).pill" x-text="tone(r).short"></span>
                        </li>
                    </template>
                </ul>
            </div>

            {{-- Reminder: small, at the bottom --}}
            <p x-show="{{ $gate ? 'true' : '!classId' }}" class="flex gap-2 text-xs text-slate-500">
                <x-icon name="clock" class="h-4 w-4 shrink-0" />
                <span>Different students can scan right after each other. The <b>same</b> student must wait 2 minutes between IN and OUT (scan IN at 7:30 → OUT from 7:32).</span>
            </p>
        </aside>
      </div>
    </div>
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        function scanner() {
            return {
                full: false,
                async enterFull() { this.full = true; try { await document.documentElement.requestFullscreen?.() } catch (e) {} this.$nextTick(() => this.$refs.box.focus()) },
                async exitFull() { this.full = false; if (document.fullscreenElement) { try { await document.exitFullscreen() } catch (e) {} } },
                code: '', classId: '{{ $selected ?: '' }}', last: null, recent: [], camera: false, cam: null, busy: false, lastCode: '', lastAt: 0,
                init() { setInterval(() => { const a = document.activeElement; if (a !== this.$refs.box && !(a && /SELECT|INPUT|TEXTAREA/.test(a.tagName))) this.$refs.box.focus() }, 1500) },
                beep(ok) { try { const a = new AudioContext(), o = a.createOscillator(); o.frequency.value = ok ? 880 : 220; o.connect(a.destination); o.start(); o.stop(a.currentTime + 0.15) } catch (e) {} },
                async send(code) {
                    code = (code || '').trim(); if (!code || this.busy) return;
                    if (code === this.lastCode && Date.now() - this.lastAt < 4000) return;
                    this.lastCode = code; this.lastAt = Date.now(); this.busy = true;
                    try {
                        const r = await fetch('{{ route('attendance.checkin') }}', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: JSON.stringify({ code, class_id: this.classId || null }) });
                        this.last = await r.json();
                    } catch (e) { this.last = { ok: false, message: 'Connection problem – try again.' } }
                    this.flash = true; clearTimeout(this.flashTimer); this.flashTimer = setTimeout(() => this.flash = false, 3500); this.beep(this.last.ok); this.recent.unshift({ ...this.last, time: new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', second: '2-digit' }), key: Date.now() + Math.random() }); this.recent = this.recent.slice(0, 30); this.tally(this.last);
                    this.code = ''; this.busy = false;
                },
                counts: { in: 0, late: 0, out: 0, err: 0 },
                tally(r) { if (!r.ok) this.counts.err++; else if (r.status === 'present') this.counts.in++; else if (r.status === 'late') this.counts.late++; else if (r.status === 'out') this.counts.out++; },
                initials(n) { return (n || '?').split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase() },
                tone(r) {
                    if (!r) return {};
                    if (!r.ok) return { label: r.status === 'inactive' ? 'Not active' : 'Not recognized', short: 'Problem', solid: 'bg-rose-600', pill: 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300', card: 'border-rose-300 bg-rose-50 dark:border-rose-500/40 dark:bg-rose-500/10' };
                    if (r.status === 'late') return { label: 'Checked IN – late', short: 'Late', solid: 'bg-amber-500', pill: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300', card: 'border-amber-300 bg-amber-50 dark:border-amber-500/40 dark:bg-amber-500/10' };
                    if (r.status === 'out') return { label: 'Checked OUT', short: 'Out', solid: 'bg-slate-700', pill: 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200', card: 'border-slate-300 bg-slate-50 dark:border-slate-600 dark:bg-slate-800' };
                    if (r.status === 'already') return { label: 'Already scanned', short: 'Repeat', solid: 'bg-blue-600', pill: 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300', card: 'border-blue-300 bg-blue-50 dark:border-blue-500/40 dark:bg-blue-500/10' };
                    return { label: 'Checked IN', short: 'In', solid: 'bg-emerald-600', pill: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300', card: 'border-emerald-300 bg-emerald-50 dark:border-emerald-500/40 dark:bg-emerald-500/10' };
                },
                camError: '', camOpen: false, cameras: [], camIndex: 0, flash: false, flashTimer: null,
                async openCam() {
                    this.camOpen = true; this.camError = '';
                    await this.startCam();
                },
                async closeCam() {
                    await this.stopCam(); this.camOpen = false; this.camError = '';
                    this.$nextTick(() => this.$refs.box.focus());
                },
                async startCam() {
                    // Browsers only allow the camera on https:// or localhost.
                    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) { this.camError = 'insecure'; return }
                    await this.$nextTick();
                    try {
                        if (!this.cameras.length) { try { this.cameras = await Html5Qrcode.getCameras() } catch (e) { this.cameras = [] } }
                        if (this.cameras.length && !this.camPicked) { const saved = localStorage.getItem('ss.scanCam'); const i = this.cameras.findIndex((c) => c.id === saved); if (i >= 0) this.camIndex = i; this.camPicked = true }
                        this.cam = new Html5Qrcode('qr-reader');
                        const source = this.cameras[this.camIndex]?.id || { facingMode: 'environment' };
                        await this.cam.start(source, { fps: 12, qrbox: (w, h) => { const m = Math.floor(Math.min(w, h) * 0.7); return { width: m, height: m } } }, (t) => this.send(t));
                        this.camera = true;
                    } catch (e) {
                        this.camera = false; this.cam = null;
                        this.camError = /NotAllowed|Permission/i.test(String(e)) ? 'denied' : /NotFound|no camera|Requested device/i.test(String(e)) ? 'none' : 'other';
                    }
                },
                async switchCam() { this.camIndex = (this.camIndex + 1) % this.cameras.length; await this.pickCam() },
                camPicked: false,
                async pickCam() { if (this.cameras[this.camIndex]) localStorage.setItem('ss.scanCam', this.cameras[this.camIndex].id); this.camError = ''; await this.stopCam(); await this.startCam() },
                async stopCam() { if (this.cam) { try { await this.cam.stop(); this.cam.clear() } catch (e) {} } this.cam = null; this.camera = false }
            }
        }
    </script>
</x-layouts.app>

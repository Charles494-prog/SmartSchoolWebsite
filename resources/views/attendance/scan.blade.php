<x-layouts.app :title="$gate ? 'Gate scanner' : 'QR scanner'">
    <div x-data="scanner()" x-init="init()" x-on:fullscreenchange.document="if (!document.fullscreenElement) full = false" x-on:keydown.escape.window="camOpen ? closeCam() : (full && exitFull())"
         :class="full ? 'fixed inset-0 z-[70] overflow-y-auto bg-slate-100 p-4 sm:p-8 dark:bg-slate-950' : ''" class="transition-colors">
      <div class="mb-4 flex items-center justify-end gap-2" :class="full && 'mx-auto max-w-7xl'">
          <span x-show="full" x-cloak class="mr-auto text-lg font-semibold text-slate-900">{{ \App\Models\Setting::get('school_name') }} · {{ $gate ? 'Gate' : 'Attendance' }}</span>
          <button type="button" class="btn min-h-11" x-on:click="full ? exitFull() : enterFull()" :aria-pressed="full">
              <span x-text="full ? 'Exit full screen' : 'Full screen'">Full screen</span>
          </button>
      </div>
      <div :class="camOpen && 'grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_380px]'">
            {{-- Camera scanner: fills the page area, menu and top bar stay visible --}}
            <div x-show="camOpen" x-cloak x-ref="camWrap" x-transition.opacity.duration.250ms class="relative flex h-[calc(100dvh-12rem)] min-h-[420px] flex-col overflow-hidden rounded-2xl bg-black text-white shadow-xl lg:sticky lg:top-24" role="region" aria-label="Camera scanner">
                <div class="relative flex-1 overflow-hidden">
                    <div id="qr-reader" class="absolute inset-0"></div>

                    {{-- Aim frame --}}
                    <div x-show="camera" class="pointer-events-none absolute inset-0 flex items-center justify-center">
                        <div class="relative aspect-square w-[min(60%,55vh)] rounded-3xl shadow-[0_0_0_9999px_rgba(0,0,0,.45)]">
                            <span class="absolute -top-1 -left-1 h-10 w-10 rounded-tl-3xl border-t-4 border-l-4 border-white"></span>
                            <span class="absolute -top-1 -right-1 h-10 w-10 rounded-tr-3xl border-t-4 border-r-4 border-white"></span>
                            <span class="absolute -bottom-1 -left-1 h-10 w-10 rounded-bl-3xl border-b-4 border-l-4 border-white"></span>
                            <span class="absolute -right-1 -bottom-1 h-10 w-10 rounded-br-3xl border-r-4 border-b-4 border-white"></span>
                            <span class="scanline absolute inset-x-6 h-0.5 rounded-full bg-brand-500/90 shadow-[0_0_12px_2px_rgba(99,102,241,.8)]"></span>
                        </div>
                    </div>

                    {{-- Top bar --}}
                    <div class="absolute inset-x-0 top-0 flex items-center gap-3 bg-gradient-to-b from-black/70 to-transparent p-4 pt-[max(1rem,env(safe-area-inset-top))]">
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-semibold">{{ $gate ? 'Gate scanner' : 'QR scanner' }}</div>
                            <div class="truncate text-xs text-white/70" x-text="classId ? 'Class attendance' : 'School gate · in and out'"></div>
                        </div>
                        <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-medium backdrop-blur" x-show="recent.length" x-text="recent.length + ' scanned'"></span>
                        <button type="button" class="flex h-11 w-11 items-center justify-center rounded-full bg-white/15 backdrop-blur hover:bg-white/25" x-show="cameras.length > 1" x-on:click="switchCam()" aria-label="Switch camera">
                            <x-icon name="repeat" class="h-5 w-5" />
                        </button>
                        <button type="button" class="flex h-11 w-11 items-center justify-center rounded-full bg-white/15 backdrop-blur hover:bg-white/25" x-on:click="closeCam()" aria-label="Close camera">
                            <x-icon name="x" class="h-5 w-5" />
                        </button>
                    </div>

                    {{-- Starting --}}
                    <div x-show="!camera && !camError" class="absolute inset-0 flex flex-col items-center justify-center gap-3 text-white/80">
                        <span class="h-8 w-8 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                        <span class="text-sm">Starting camera…</span>
                    </div>

                    {{-- Errors --}}
                    <div x-show="camError" class="absolute inset-0 flex items-center justify-center p-4">
                        <div x-show="camError" x-cloak role="alert" class="max-h-[80vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-5 text-sm text-slate-700 shadow-2xl dark:bg-slate-900">
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

                    {{-- Last result --}}
                    <div class="absolute inset-x-0 bottom-0 p-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
                        <div x-show="last && flash" x-transition:enter="transition duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
                             class="mx-auto flex max-w-md items-center gap-4 rounded-2xl px-5 py-4 shadow-2xl"
                             :class="{ 'bg-emerald-600': last && last.status==='present', 'bg-slate-800': last && last.status==='out', 'bg-amber-500': last && last.status==='late', 'bg-blue-600': last && last.status==='already', 'bg-rose-600': last && !last.ok }">
                            <x-icon name="check-circle" class="h-8 w-8 shrink-0" x-show="last && last.ok" />
                            <x-icon name="alert" class="h-8 w-8 shrink-0" x-show="last && !last.ok" />
                            <div class="min-w-0">
                                <div class="truncate text-xl font-bold" x-text="last ? (last.name || 'Unknown') : ''"></div>
                                <div class="text-sm opacity-90" x-text="last ? last.message + (last.section ? ' · ' + last.section : '') : ''"></div>
                            </div>
                        </div>
                        <p x-show="camera && !(last && flash)" class="text-center text-sm text-white/80">Point the camera at the QR code on the ID card</p>
                    </div>
                </div>
            </div>
            </div>
      <div class="grid gap-4" :class="camOpen ? 'mt-4 lg:mt-0' : (full ? 'mx-auto max-w-7xl lg:grid-cols-[2fr_3fr]' : 'lg:grid-cols-2')">
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
            <button type="button" class="btn mt-4 min-h-12 w-full text-base" x-show="!camOpen" x-on:click="openCam()">
                <x-icon name="qr" class="h-5 w-5" /> Scan with camera
            </button>

        </div>
        <div class="space-y-3">
            <div :class="full && !camOpen ? 'min-h-[50vh]' : 'min-h-48'" class="card flex flex-col items-center justify-center text-center transition"
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
    </div>
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        function scanner() {
            return {
                full: false,
                async enterFull() { this.full = true; try { await document.documentElement.requestFullscreen?.() } catch (e) {} this.$nextTick(() => this.$refs.box.focus()) },
                async exitFull() { this.full = false; if (document.fullscreenElement) { try { await document.exitFullscreen() } catch (e) {} } },
                code: '', classId: '{{ $selected ?: '' }}', last: null, recent: [], camera: false, cam: null, busy: false, lastCode: '', lastAt: 0,
                init() { setInterval(() => { if (!this.camOpen && document.activeElement !== this.$refs.box) this.$refs.box.focus() }, 1500) },
                beep(ok) { try { const a = new AudioContext(), o = a.createOscillator(); o.frequency.value = ok ? 880 : 220; o.connect(a.destination); o.start(); o.stop(a.currentTime + 0.15) } catch (e) {} },
                async send(code) {
                    code = (code || '').trim(); if (!code || this.busy) return;
                    if (code === this.lastCode && Date.now() - this.lastAt < 4000) return;
                    this.lastCode = code; this.lastAt = Date.now(); this.busy = true;
                    try {
                        const r = await fetch('{{ route('attendance.checkin') }}', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: JSON.stringify({ code, class_id: this.classId || null }) });
                        this.last = await r.json();
                    } catch (e) { this.last = { ok: false, message: 'Connection problem – try again.' } }
                    this.flash = true; clearTimeout(this.flashTimer); this.flashTimer = setTimeout(() => this.flash = false, 3500); this.beep(this.last.ok); this.recent.unshift({ ...this.last, time: new Date().toLocaleTimeString() }); this.recent = this.recent.slice(0, 15);
                    this.code = ''; this.busy = false;
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
                        this.cam = new Html5Qrcode('qr-reader');
                        const source = this.cameras.length > 1 ? this.cameras[this.camIndex].id : { facingMode: 'environment' };
                        await this.cam.start(source, { fps: 12, qrbox: (w, h) => { const m = Math.floor(Math.min(w, h) * 0.7); return { width: m, height: m } } }, (t) => this.send(t));
                        this.camera = true;
                    } catch (e) {
                        this.camera = false; this.cam = null;
                        this.camError = /NotAllowed|Permission/i.test(String(e)) ? 'denied' : /NotFound|no camera|Requested device/i.test(String(e)) ? 'none' : 'other';
                    }
                },
                async switchCam() { await this.stopCam(); this.camIndex = (this.camIndex + 1) % this.cameras.length; await this.startCam() },
                async stopCam() { if (this.cam) { try { await this.cam.stop(); this.cam.clear() } catch (e) {} } this.cam = null; this.camera = false }
            }
        }
    </script>
</x-layouts.app>

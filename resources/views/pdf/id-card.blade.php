@php
    $school = \App\Models\Setting::get('school_name');
    $logoPath = \App\Models\Setting::get('logo_path');
    $logo = $logoPath && \Illuminate\Support\Facades\Storage::disk('local')->exists($logoPath)
        ? 'data:'.(\Illuminate\Support\Facades\Storage::disk('local')->mimeType($logoPath) ?: 'image/png').';base64,'.base64_encode(\Illuminate\Support\Facades\Storage::disk('local')->get($logoPath)) : null;
@endphp
<html><head>@include('pdf._head')
<style>
    @page { margin: 22px 24px; }
    /* CR80 card size: 243 x 153 pt. Laid out with tables (dompdf-friendly) so text never overlaps. */
    .card { width: 241px; height: 151px; border: 1px solid #cbd5e1; border-radius: 6px; display: inline-block; margin: 4px; overflow: hidden; vertical-align: top; }
    .top { background: #0f172a; color: #fff; height: 20px; }
    .top td { padding: 0 7px; height: 20px; vertical-align: middle; }
    .top img { width: 14px; height: 14px; }
    .school { font-weight: bold; font-size: 8px; letter-spacing: .3px; white-space: nowrap; overflow: hidden; }
    .body td { vertical-align: top; }
    .photo { width: 58px; height: 58px; border: 1px solid #cbd5e1; }
    .nophoto { width: 56px; height: 34px; border: 1px dashed #94a3b8; color: #94a3b8; font-size: 6.5px; text-align: center; padding-top: 22px; }
    .name { font-weight: bold; line-height: 1.15; word-wrap: break-word; }
    .lbl { color: #64748b; font-size: 5.5px; letter-spacing: .4px; }
    .val { font-weight: bold; font-size: 8px; }
    .qr { width: 70px; height: 70px; }
</style></head><body>
@foreach($students as $s)
    @php
        $e = $enrollments[$s->id] ?? null;
        $photo = \App\Support\Photo::dataUri($s->photo_path);
        $name = trim(preg_replace('/\s+/', ' ', $s->first_name.' '.($s->middle_name ? mb_substr($s->middle_name, 0, 1).'.' : '').' '.$s->last_name.' '.$s->suffix));
        $size = mb_strlen($name) > 30 ? 7 : (mb_strlen($name) > 20 ? 8 : 9.5);
    @endphp
    <div class="card">
        <table class="top" width="100%" cellpadding="0" cellspacing="0"><tr>
            @if($logo)<td style="width:14px;padding-right:0"><img src="{{ $logo }}"></td>@endif
            <td class="school">{{ $school }}</td>
        </tr></table>
        <table class="body" width="100%" cellpadding="0" cellspacing="0" style="margin-top:6px"><tr>
            <td style="width:66px;padding-left:7px">@if($photo)<img class="photo" src="{{ $photo }}">@else<div class="nophoto">1×1 PHOTO</div>@endif</td>
            <td style="padding:0 4px">
                <div class="name" style="font-size: {{ $size }}px">{{ $name }}</div>
                <div style="margin-top:5px" class="lbl">SCHOOL ID NO.</div>
                <div class="val">{{ $s->student_no }}</div>
            </td>
            <td style="width:74px;padding-right:6px;text-align:right"><img class="qr" src="{{ \App\Support\Qr::dataUri($s->qr_token, 160) }}"></td>
        </tr></table>
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:8px"><tr>
            <td style="width:58%;padding-left:7px;vertical-align:top"><div class="lbl">GRADE &amp; SECTION</div><div class="val">{{ $e?->gradeLevel?->name }}{{ $e?->section?->name ? ' – '.$e->section->name : '' }}{{ $e ? '' : '—' }}</div></td>
            <td style="padding-right:7px;vertical-align:top"><div class="lbl">LRN</div><div class="val">{{ $s->lrn ?: '—' }}</div></td>
        </tr></table>
    </div>
@endforeach
</body></html>

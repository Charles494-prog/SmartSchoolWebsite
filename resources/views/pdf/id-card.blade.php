<html><head>@include('pdf._head')
<style>
    @page { margin: 22px 24px; }
    .card { width: 242px; height: 153px; border: 1px solid #cbd5e1; border-radius: 6px; display: inline-block; margin: 4px; overflow: hidden; vertical-align: top; position: relative; }
    .top { background: #0f172a; color: #fff; padding: 5px 8px; font-weight: bold; font-size: 8.5px; letter-spacing: .3px; height: 12px; overflow: hidden; white-space: nowrap; }
    .photo { position: absolute; left: 8px; top: 30px; width: 64px; height: 64px; border: 1px solid #cbd5e1; }
    .nophoto { position: absolute; left: 8px; top: 30px; width: 64px; height: 64px; border: 1px dashed #94a3b8; color: #94a3b8; font-size: 7px; text-align: center; padding-top: 27px; height: 37px; }
    .qr { position: absolute; right: 7px; top: 30px; width: 78px; height: 78px; }
    .name { position: absolute; left: 80px; right: 90px; top: 30px; font-weight: bold; font-size: 10px; line-height: 12px; max-height: 36px; overflow: hidden; }
    .meta { position: absolute; left: 8px; right: 8px; bottom: 7px; font-size: 7.5px; }
    .lbl { color: #64748b; font-size: 6px; letter-spacing: .4px; }
    .val { font-weight: bold; font-size: 8.5px; }
    .info { position: absolute; left: 80px; top: 68px; width: 75px; }
</style></head><body>
@foreach($students as $s)
    @php $e = $enrollments[$s->id] ?? null; $photo = \App\Support\Photo::dataUri($s->photo_path); @endphp
    <div class="card">
        <div class="top">{{ \App\Models\Setting::get('school_name') }}</div>
        @if($photo)<img class="photo" src="{{ $photo }}">@else<div class="nophoto">1×1 PHOTO</div>@endif
        <div class="name">{{ $s->first_name }} {{ $s->middle_name ? mb_substr($s->middle_name, 0, 1).'.' : '' }} {{ $s->last_name }} {{ $s->suffix }}</div>
        <div class="info"><div class="lbl">SCHOOL ID NO.</div><div class="val">{{ $s->student_no }}</div></div>
        <img class="qr" src="{{ \App\Support\Qr::dataUri($s->qr_token, 160) }}">
        <div class="meta">
            <table><tr>
                <td style="width:50%"><div class="lbl">GRADE & SECTION</div><div class="val">{{ $e?->gradeLevel?->name }} {{ $e?->section?->name ? '– '.$e->section->name : '' }}</div></td>
                <td style="width:50%"><div class="lbl">LRN</div><div class="val">{{ $s->lrn ?: '—' }}</div></td>
            </tr></table>
        </div>
    </div>
@endforeach
</body></html>

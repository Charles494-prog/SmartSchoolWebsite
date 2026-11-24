@php $logo = \App\Models\Setting::get('logo_path'); @endphp
<table class="head" style="margin-bottom:8px"><tr>
    <td style="width:60px">@if($logo && \Illuminate\Support\Facades\Storage::disk('local')->exists($logo))<img src="data:image/png;base64,{{ base64_encode(\Illuminate\Support\Facades\Storage::disk('local')->get($logo)) }}" style="height:52px">@endif</td>
    <td class="c">
        <h1>{{ \App\Models\Setting::get('school_name') }}</h1>
        <div class="muted">{{ \App\Models\Setting::get('school_address') }}</div>
        @isset($docTitle)<div class="b" style="margin-top:4px;font-size:13px">{{ $docTitle }}</div>@endisset
    </td>
    <td style="width:60px"></td>
</tr></table>

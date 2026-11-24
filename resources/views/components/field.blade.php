@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'options' => [], 'required' => false, 'help' => null, 'placeholder' => null])
@php
    $id = 'f_'.str_replace(['[', ']', '.'], '_', $name);
    $dot = str_replace(['[', ']'], ['.', ''], $name);
    $val = old($dot, $value);
@endphp
<div {{ $attributes->only('class') }}>
    @if($type === 'checkbox')
        <input type="hidden" name="{{ $name }}" value="0">
        <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
            <input type="checkbox" id="{{ $id }}" name="{{ $name }}" value="1" @checked($val) class="h-4 w-4 rounded border-slate-300 text-brand-600"> {{ $label }}
        </label>
    @else
        @if($label)<label for="{{ $id }}" class="label">{{ $label }}@if($required)<span class="text-red-600"> *</span>@endif</label>@endif
        @if($type === 'select')
            <select id="{{ $id }}" name="{{ $name }}" class="input @error($dot) border-red-500 @enderror" {{ $attributes->except('class') }}>
                @unless($required)<option value="">—</option>@endunless
                @foreach($options as $k => $v)
                    <option value="{{ $k }}" @selected((string) $val === (string) $k)>{{ $v }}</option>
                @endforeach
            </select>
        @elseif($type === 'textarea')
            <textarea id="{{ $id }}" name="{{ $name }}" rows="4" class="input @error($dot) border-red-500 @enderror" placeholder="{{ $placeholder }}" {{ $attributes->except('class') }}>{{ $val }}</textarea>
        @else
            <input id="{{ $id }}" type="{{ $type }}" name="{{ $name }}" value="{{ $type === 'password' || $type === 'file' ? '' : $val }}" placeholder="{{ $placeholder }}" class="input @error($dot) border-red-500 @enderror" {{ $attributes->except('class') }}>
        @endif
    @endif
    @if($help)<p class="mt-1 text-xs text-slate-500">{{ $help }}</p>@endif
    @error($dot)<p class="field-error">{{ $message }}</p>@enderror
</div>

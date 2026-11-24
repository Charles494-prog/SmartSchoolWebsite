@props(['icon' => 'sparkles', 'title' => 'Nothing here yet', 'text' => null])
<div class="flex flex-col items-center px-6 py-14 text-center">
    <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 text-brand-500"><x-icon :name="$icon" class="h-7 w-7" /></div>
    <div class="font-semibold text-slate-900">{{ $title }}</div>
    @if($text)<p class="mt-1 max-w-sm text-sm text-slate-500">{{ $text }}</p>@endif
    @if(trim($slot))<div class="mt-4">{{ $slot }}</div>@endif
</div>

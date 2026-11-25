{{-- Browser-tab icon: the school's own logo when one is set, otherwise the default icon. --}}
@if($logo)
    <link rel="icon" href="{{ $logo }}">
    <link rel="apple-touch-icon" href="{{ $logo }}">
@else
    <link rel="icon" href="{{ asset('favicon.ico') }}">
@endif

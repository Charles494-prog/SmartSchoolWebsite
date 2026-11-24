@if($children->count() > 1)
    <div class="mb-4 flex flex-wrap gap-2">
        @foreach($children as $c)<a href="?child={{ $c->id }}" class="btn btn-sm {{ $child?->id === $c->id ? 'btn-primary' : '' }}">{{ $c->first_name }}</a>@endforeach
    </div>
@endif
@if(! $child)
    <div class="card"><p>No student is linked to this account yet. Please contact the school registrar.</p></div>
@endif

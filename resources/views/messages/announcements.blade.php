<x-layouts.app title="Announcements">
    <h1 class="h1 mb-4">Announcements</h1>
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-3 lg:col-span-2">
            @forelse($items as $a)
                <div class="card">
                    <div class="flex items-start gap-2">
                        <div class="flex-1"><div class="font-semibold">@if($a->is_pinned)<x-icon name="pin" class="mr-1 inline h-4 w-4 text-brand-500" />@endif{{ $a->title }}</div>
                            <div class="muted text-xs">{{ $a->published_at?->format('M d, Y g:i A') }} · {{ $a->author?->name }} · {{ \App\Models\Announcement::AUDIENCES[$a->audience] }}{{ $a->section ? ' · '.$a->section->name : '' }}</div></div>
                        @if(auth()->user()->isAdmin() || $a->author_id === auth()->id())
                            <form method="post" action="{{ route('announcements.destroy', $a) }}" onsubmit="return confirm('Delete?')">@csrf @method('delete')<button class="text-xs text-red-600">Delete</button></form>
                        @endif
                    </div>
                    <p class="mt-2 whitespace-pre-line text-sm">{{ $a->body }}</p>
                </div>
            @empty
                <p class="card muted">No announcements.</p>
            @endforelse
            {{ $items->links() }}
        </div>
        @if($canPost)
            <div class="card h-fit">
                <h2 class="h2 mb-3">Post announcement</h2>
                <form method="post" action="{{ route('announcements.store') }}" class="space-y-3">
                    @csrf
                    <x-field name="title" label="Title" required />
                    <x-field name="body" label="Message" type="textarea" required />
                    <x-field name="audience" label="Who can see it" type="select" :options="\App\Models\Announcement::AUDIENCES" :value="auth()->user()->role === 'teacher' ? 'parents' : 'all'" required />
                    <x-field name="section_id" label="{{ auth()->user()->role === 'teacher' ? 'Section' : 'Only this section (optional)' }}" type="select" :options="$sections" :required="auth()->user()->role === 'teacher'" />
                    <x-field name="is_pinned" label="Pin to top" type="checkbox" :value="false" />
                    <x-field name="email" label="Also send by email" type="checkbox" :value="false" />
                    <button class="btn btn-primary w-full">Post</button>
                </form>
            </div>
        @endif
    </div>
</x-layouts.app>

<x-layouts.app title="Notifications">
    <h1 class="h1 mb-4">Notifications</h1>
    <div class="card divide-y p-0">
        @forelse($list as $n)
            <a href="{{ $n->data['url'] ?? '#' }}" class="block px-4 py-3 hover:bg-slate-50 {{ $n->read_at ? '' : 'bg-brand-50' }}">
                <div class="font-semibold">{{ $n->data['title'] ?? 'Notice' }}</div>
                <div class="text-sm">{{ $n->data['body'] ?? '' }}</div>
                <div class="muted text-xs">{{ $n->created_at->diffForHumans() }}</div>
            </a>
        @empty
            <p class="p-6 text-center text-slate-500">No notifications.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $list->links() }}</div>
</x-layouts.app>

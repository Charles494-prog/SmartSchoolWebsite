<x-layouts.app title="Library">
    <h1 class="h1 mb-4">Library</h1>
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <div class="card">
                <h2 class="h2 mb-3">Borrowed books ({{ $loans->count() }})</h2>
                <table class="table">
                    <thead><tr><th>Book</th><th>Borrower</th><th>Borrowed</th><th>Due</th><th></th></tr></thead>
                    <tbody>
                    @forelse($loans as $l)
                        <tr class="{{ $l->isOverdue() ? 'bg-red-50' : '' }}">
                            <td>{{ $l->book->title }}</td><td>{{ $l->borrowerName() }}</td><td>{{ $l->borrowed_on->format('M d') }}</td>
                            <td class="{{ $l->isOverdue() ? 'font-semibold text-red-700' : '' }}">{{ $l->due_on->format('M d') }}{{ $l->isOverdue() ? ' (overdue)' : '' }}</td>
                            <td class="text-right"><form method="post" action="{{ route('library.return', $l) }}">@csrf<button class="btn btn-sm">Return</button></form></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">No books out.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card overflow-x-auto">
                <form class="mb-3 flex gap-2"><h2 class="h2 mr-auto">Catalog</h2><input name="q" value="{{ request('q') }}" class="input max-w-xs" placeholder="Title, author, ISBN…"><button class="btn">Search</button></form>
                <table class="table">
                    <thead><tr><th>Title</th><th>Author</th><th>Category</th><th>Shelf</th><th>Available</th><th></th></tr></thead>
                    <tbody>
                    @forelse($books as $b)
                        <tr><td>{{ $b->title }}</td><td>{{ $b->author }}</td><td>{{ $b->category }}</td><td>{{ $b->shelf }}</td><td>{{ $b->copies - $b->out_count }} / {{ $b->copies }}</td><td><a class="link" href="?edit={{ $b->id }}">Edit</a></td></tr>
                    @empty
                        <tr><td colspan="6" class="muted">No books yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
                {{ $books->links() }}
            </div>
        </div>
        <div class="space-y-4">
            <div class="card">
                <h2 class="h2 mb-3">Lend a book</h2>
                <form method="post" action="{{ route('library.borrow') }}" class="space-y-3">
                    @csrf
                    <x-field name="book_id" label="Book" type="select" :options="\App\Models\Book::orderBy('title')->pluck('title', 'id')" required />
                    <x-field name="student_id" label="Student" type="select" :options="$students" required />
                    <button class="btn btn-primary w-full">Lend ({{ \App\Models\Setting::get('library_loan_days') }} days)</button>
                </form>
            </div>
            <div class="card">
                <h2 class="h2 mb-3">{{ $edit ? 'Edit book' : 'Add book' }}</h2>
                <form method="post" action="{{ $edit ? route('library.books.update', $edit) : route('library.books.store') }}" class="space-y-3">
                    @csrf
                    <x-field name="title" label="Title" :value="$edit?->title" required />
                    <x-field name="author" label="Author" :value="$edit?->author" />
                    <x-field name="isbn" label="ISBN" :value="$edit?->isbn" />
                    <x-field name="publisher" label="Publisher" :value="$edit?->publisher" />
                    <x-field name="category" label="Category" :value="$edit?->category" />
                    <x-field name="copies" label="Copies" type="number" :value="$edit?->copies ?? 1" required />
                    <x-field name="shelf" label="Shelf" :value="$edit?->shelf" />
                    <button class="btn btn-primary w-full">Save</button>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>

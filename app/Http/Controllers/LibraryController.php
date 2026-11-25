<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookLoan;
use App\Models\Setting;
use App\Models\Student;
use Illuminate\Http\Request;

class LibraryController extends Controller
{
    public function index(Request $request)
    {
        $q = Book::withCount(['loans as out_count' => fn ($l) => $l->whereNull('returned_on')]);
        if ($t = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('title', 'like', "%$t%")->orWhere('author', 'like', "%$t%")->orWhere('isbn', 'like', "%$t%")->orWhere('category', 'like', "%$t%")->orWhere('shelf', 'like', "%$t%"));
        }
        $books = $q->orderBy('title')->get();
        $group = $request->query('group') === 'shelf' ? 'shelf' : 'category';

        return view('library.index', [
            'books' => $books, 'group' => $group,
            'grouped' => $books->groupBy(fn ($b) => $group === 'shelf' ? ($b->shelf ? 'Shelf '.strtoupper(preg_replace('/\d+$/', '', $b->shelf) ?: $b->shelf) : 'No shelf') : ($b->category ?: 'Uncategorized'))->sortKeys(),
            'stats' => ['titles' => $books->count(), 'copies' => $books->sum('copies'), 'out' => BookLoan::whereNull('returned_on')->count(), 'late' => BookLoan::whereNull('returned_on')->whereDate('due_on', '<', today())->count()],
            'loans' => BookLoan::with('book', 'student', 'user')->whereNull('returned_on')->orderBy('due_on')->get(),
            'students' => Student::where('status', 'active')->orderBy('last_name')->get()->mapWithKeys(fn ($s) => [$s->id => $s->full_name.' ('.$s->student_no.')']),
            'edit' => $request->filled('edit') ? Book::find($request->edit) : null,
        ]);
    }

    public function saveBook(Request $request, ?Book $book = null)
    {
        $data = $request->validate([
            'title' => 'required|max:200', 'author' => 'nullable|max:150', 'isbn' => 'nullable|max:20', 'publisher' => 'nullable|max:150',
            'category' => 'nullable|max:80', 'copies' => 'required|integer|min:1|max:999', 'shelf' => 'nullable|max:30', 'cover' => 'nullable|image|max:4096',
        ]);
        if ($request->hasFile('cover')) {
            $data['cover_path'] = $request->file('cover')->store('book-covers', 'local');
        }
        unset($data['cover']);
        ($book ?? new Book)->fill($data)->save();

        return redirect()->route('library.index')->with('ok', 'Book saved.');
    }

    public function borrow(Request $request)
    {
        $data = $request->validate([
            'book_id' => 'required|exists:books,id', 'student_id' => 'required|exists:students,id',
            'due_on' => 'nullable|date|after_or_equal:today', 'copies' => 'nullable|integer|min:1|max:10',
            'condition' => 'nullable|in:new,good,fair,worn', 'note' => 'nullable|max:255',
        ], ['due_on.after_or_equal' => 'The due date cannot be in the past.']);
        $book = Book::findOrFail($data['book_id']);
        $copies = (int) ($data['copies'] ?? 1);
        if ($book->available() < $copies) {
            return back()->with('error', 'Only '.$book->available().' '.str('copy')->plural($book->available()).' available.');
        }
        $due = isset($data['due_on']) ? \Carbon\Carbon::parse($data['due_on']) : today()->addDays((int) Setting::get('library_loan_days'));
        BookLoan::create(['book_id' => $book->id, 'student_id' => $data['student_id'], 'borrowed_on' => today(), 'due_on' => $due, 'copies' => $copies,
            'condition' => $data['condition'] ?? null, 'note' => $data['note'] ?? null]);

        return back()->with('ok', 'Book lent. Due '.$due->format('M d, Y').'.');
    }

    public function return(BookLoan $loan)
    {
        $daysLate = max(0, $loan->due_on->diffInDays(today(), false));
        $loan->update(['returned_on' => today(), 'fine' => $daysLate * (float) Setting::get('library_fine_per_day')]);

        return back()->with('ok', 'Returned.'.($loan->fine > 0 ? ' Late fine: ₱'.number_format($loan->fine, 2) : ''));
    }

    public function cover(Book $book)
    {
        abort_unless($book->cover_path, 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->response($book->cover_path);
    }
}

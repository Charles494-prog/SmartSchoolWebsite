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
            $q->where(fn ($w) => $w->where('title', 'like', "%$t%")->orWhere('author', 'like', "%$t%")->orWhere('isbn', 'like', "%$t%"));
        }

        return view('library.index', [
            'books' => $q->orderBy('title')->paginate(25)->withQueryString(),
            'loans' => BookLoan::with('book', 'student', 'user')->whereNull('returned_on')->orderBy('due_on')->get(),
            'students' => Student::where('status', 'active')->orderBy('last_name')->get()->mapWithKeys(fn ($s) => [$s->id => $s->full_name.' ('.$s->student_no.')']),
            'edit' => $request->filled('edit') ? Book::find($request->edit) : null,
        ]);
    }

    public function saveBook(Request $request, ?Book $book = null)
    {
        $data = $request->validate([
            'title' => 'required|max:200', 'author' => 'nullable|max:150', 'isbn' => 'nullable|max:20', 'publisher' => 'nullable|max:150',
            'category' => 'nullable|max:80', 'copies' => 'required|integer|min:1|max:999', 'shelf' => 'nullable|max:30',
        ]);
        ($book ?? new Book)->fill($data)->save();

        return redirect()->route('library.index')->with('ok', 'Book saved.');
    }

    public function borrow(Request $request)
    {
        $data = $request->validate(['book_id' => 'required|exists:books,id', 'student_id' => 'required|exists:students,id']);
        $book = Book::findOrFail($data['book_id']);
        if ($book->available() < 1) {
            return back()->with('error', 'No copies available.');
        }
        BookLoan::create($data + ['borrowed_on' => today(), 'due_on' => today()->addDays((int) Setting::get('library_loan_days'))]);

        return back()->with('ok', 'Book lent. Due '.today()->addDays((int) Setting::get('library_loan_days'))->format('M d, Y').'.');
    }

    public function return(BookLoan $loan)
    {
        $daysLate = max(0, $loan->due_on->diffInDays(today(), false));
        $loan->update(['returned_on' => today(), 'fine' => $daysLate * (float) Setting::get('library_fine_per_day')]);

        return back()->with('ok', 'Returned.'.($loan->fine > 0 ? ' Late fine: ₱'.number_format($loan->fine, 2) : ''));
    }
}

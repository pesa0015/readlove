<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Validator;
use App\Book;
use App\Bookshelf;
use App\User;
use Auth;
use Carbon\Carbon;

class BookController extends Controller
{
    public function index(Request $request)
    {
        dd(User::getUser($request));
        if (isset($request->title)) {
            $title = $request->title;
            if (strlen($title) >= 2) {
                $books = Book::select('books.id', 'title', 'title_slug', 'bookshelves.user_id')
                             ->where('title', 'LIKE', "%{$title}%")
                             ->leftJoin('bookshelves', 'books.id', '=', 'bookshelves.book_id')
                             ->limit(50)
                             ->get();

                return response()->json($books);
            }
        }
        // $myBooks = Bookshelf::with('book')->where('bookshelfes.user_id', )
    }

    public function save(Request $request)
    {
        $book_id = $request->book_id;

        $book = Bookshelf::where('user_id', Auth::user()->id)->where('book_id', $book_id)->get();
        
        if (!$book->isEmpty()) {
            return response()->json(['success' => false]);
        }
        
        $book = new Bookshelf;
        $book->user_id = Auth::user()->id;
        $book->book_id = $book_id;
        $book->timestamp = \Carbon\Carbon::now();
        $book->save();
        return response()->json(['success' => true, 'book_id' => $book_id]);
    }

    public function remove(Request $request)
    {
        $book_id = $request->book_id;

        $book = Bookshelf::where('user_id', Auth::user()->id)->where('book_id', $book_id)->first();
        
        if (!$book) {
            return response()->json(['success' => false]);
        }

        $book->delete();
        return response()->json(['success' => true, 'book_id' => $book_id]);
    }

    public function showBook($slug)
    {
        $book = Book::where('title_slug', $slug)->first();
        $saved = Bookshelf::where('user_id', Auth::user()->id)->where('book_id', $book->id)->first();
        $is_saved = false;
        if ($saved && $saved->user_id == Auth::user()->id) {
            $is_saved = true;
        }
        return view('book', ['book' => $book, 'saved' => $is_saved]);
    }
}

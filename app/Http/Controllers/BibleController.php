<?php

namespace App\Http\Controllers;

use App\Models\BibleBook;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BibleController extends Controller
{
    public function index(Request $request): View
    {
        $books = BibleBook::orderBy('sort_order')->get();
        $book = $request->filled('book')
            ? BibleBook::where('id', $request->integer('book'))->first()
            : $books->first();

        $chapter = max(1, $request->integer('chapter', 1));
        $verses = collect();

        if ($book) {
            $chapter = min($chapter, $book->chapters_count);
            $verses = $book->verses()->where('chapter', $chapter)->orderBy('verse')->get();
        }

        return view('bible.index', [
            'books' => $books,
            'book' => $book,
            'chapter' => $chapter,
            'verses' => $verses,
        ]);
    }
}

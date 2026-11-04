<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\LibraryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LibraryController extends Controller
{
    /** GET /api/library : the user's saved books (optionally ?favorites=1). */
    public function index(Request $request): JsonResponse
    {
        $query = LibraryItem::with('book')->where('user_id', $request->user()->id);

        if ($request->boolean('favorites')) {
            $query->where('is_favorite', true);
        }

        return response()->json($query->latest()->get());
    }

    /** POST /api/library  { book_id } */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['book_id' => ['required', 'exists:books,id']]);

        $item = LibraryItem::firstOrCreate([
            'user_id' => $request->user()->id,
            'book_id' => $data['book_id'],
        ]);

        return response()->json($item->load('book'), $item->wasRecentlyCreated ? 201 : 200);
    }

    /** PATCH /api/library/{book}/favorite : toggles the favorite flag. */
    public function toggleFavorite(Request $request, Book $book): JsonResponse
    {
        $item = LibraryItem::where('user_id', $request->user()->id)
            ->where('book_id', $book->id)->first();

        if (! $item) {
            return response()->json(['message' => 'Book is not in your library.'], 404);
        }

        $item->update(['is_favorite' => ! $item->is_favorite]);

        return response()->json($item->load('book'));
    }

    /** DELETE /api/library/{book} */
    public function destroy(Request $request, Book $book): JsonResponse
    {
        LibraryItem::where('user_id', $request->user()->id)
            ->where('book_id', $book->id)->delete();

        return response()->json(['message' => 'Removed from your library.']);
    }
}

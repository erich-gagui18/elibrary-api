<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookController extends Controller
{
    /** GET /api/books?q=&category=&author=&per_page= */
    public function index(Request $request): JsonResponse
    {
        $query = Book::query();

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($w) use ($search) {
                $w->where('title', 'like', "%{$search}%")
                  ->orWhere('author', 'like', "%{$search}%");
            });
        }
        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }
        if ($author = $request->query('author')) {
            $query->where('author', 'like', "%{$author}%");
        }

        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        return response()->json($query->orderBy('title')->paginate($perPage));
    }

    /** GET /api/books/{book} */
    public function show(Book $book): JsonResponse
    {
        return response()->json($book);
    }

    /** GET /api/categories */
    public function categories(): JsonResponse
    {
        $rows = Book::query()
            ->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')
            ->orderBy('category')
            ->get();

        return response()->json($rows);
    }
}

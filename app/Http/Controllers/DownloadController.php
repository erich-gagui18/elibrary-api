<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Download;
use App\Models\GuestSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DownloadController extends Controller
{
    /**
     * GET /api/books/{book}/download
     *
     * Limits are enforced HERE on the server (never trusted from the client):
     *  - registered user: config('elibrary.member_download_limit') per account
     *  - guest:           config('elibrary.guest_download_limit') per guest session
     * Every successful download counts, including repeats of the same book.
     */
    public function download(Request $request, Book $book): JsonResponse
    {
        // 'sanctum' guard works here even though this route has no auth middleware.
        $user = $request->user('sanctum');
        $guest = null;

        if ($user) {
            $limit = config('elibrary.member_download_limit');
            $column = 'user_id';
            $ownerId = $user->id;
        } else {
            $token = $request->header('X-Guest-Token');
            $guest = $token ? GuestSession::where('token', $token)->first() : null;

            if (! $guest) {
                return response()->json([
                    'message' => 'Start a guest session or log in to download.',
                ], 401);
            }

            $limit = config('elibrary.guest_download_limit');
            $column = 'guest_session_id';
            $ownerId = $guest->id;
        }

        return DB::transaction(function () use ($request, $book, $user, $guest, $limit, $column, $ownerId) {
            $used = Download::where($column, $ownerId)->lockForUpdate()->count();

            if ($used >= $limit) {
                return response()->json([
                    'message'   => $user
                        ? "Download limit reached ({$limit} per account)."
                        : "Guest download limit reached ({$limit}). Register to get more.",
                    'limit'     => $limit,
                    'used'      => $used,
                    'remaining' => 0,
                ], 429);
            }

            Download::create([
                'user_id'          => $user?->id,
                'guest_session_id' => $guest?->id,
                'book_id'          => $book->id,
                'ip_address'       => $request->ip(),
            ]);

            return response()->json([
                'message'      => 'Download authorized.',
                'download_url' => $book->file_url,
                'title'        => $book->title,
                'limit'        => $limit,
                'used'         => $used + 1,
                'remaining'    => $limit - ($used + 1),
            ]);
        });
    }

    /** GET /api/downloads/status : how many downloads are left for the caller. */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

        if ($user) {
            $limit = config('elibrary.member_download_limit');
            $used = Download::where('user_id', $user->id)->count();
        } else {
            $guest = GuestSession::where('token', $request->header('X-Guest-Token'))->first();
            if (! $guest) {
                return response()->json(['message' => 'No guest session.'], 401);
            }
            $limit = config('elibrary.guest_download_limit');
            $used = Download::where('guest_session_id', $guest->id)->count();
        }

        return response()->json([
            'limit' => $limit, 'used' => $used, 'remaining' => max(0, $limit - $used),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\GuestSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class GuestController extends Controller
{
    /**
     * Start a guest session. The client stores the returned token and sends it
     * as the X-Guest-Token header. Guest downloads are counted against it.
     */
    public function start(): JsonResponse
    {
        $session = GuestSession::create(['token' => Str::random(48)]);

        return response()->json([
            'message' => 'Guest session started.',
            'token'   => $session->token,
            'limit'   => config('elibrary.guest_download_limit'),
        ], 201);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class LiveStreamController extends Controller
{
    /**
     * Generate a LiveKit token for the authenticated user to join/create a room.
     */
    public function token(Request $request)
    {
        $request->validate([
            'room' => ['required', 'string', 'max:100'],
        ]);

        $apiKey    = config('services.livekit.key');
        $apiSecret = config('services.livekit.secret');

        if (empty($apiKey) || empty($apiSecret)) {
            return response()->json(['error' => 'LiveKit není nakonfigurován.'], 503);
        }

        $user     = Auth::user();
        $roomName = $request->input('room');

        // Build JWT token for LiveKit
        $header = $this->base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));

        $now = time();
        $claims = [
            'iss'   => $apiKey,
            'sub'   => (string) $user->id,
            'iat'   => $now,
            'nbf'   => $now,
            'exp'   => $now + 3600, // 1 hour
            'name'  => $user->name,
            'jti'   => Str::uuid()->toString(),
            'video' => [
                'room'       => $roomName,
                'roomJoin'   => true,
                'canPublish' => true,
                'canSubscribe' => true,
                'canPublishData' => true,
            ],
            'metadata' => json_encode([
                'avatar'   => $user->avatar,
                'username' => $user->username,
            ]),
        ];

        $payload   = $this->base64url(json_encode($claims));
        $signature = $this->base64url(hash_hmac('sha256', "$header.$payload", $apiSecret, true));
        $token     = "$header.$payload.$signature";

        return response()->json([
            'token' => $token,
            'url'   => config('services.livekit.url'),
            'room'  => $roomName,
        ]);
    }

    /**
     * List active rooms via LiveKit API.
     */
    public function rooms()
    {
        $apiKey    = config('services.livekit.key');
        $apiSecret = config('services.livekit.secret');
        $url       = config('services.livekit.url');

        if (empty($apiKey) || empty($apiSecret) || empty($url)) {
            return response()->json(['rooms' => []]);
        }

        // Convert wss:// to https:// for REST API
        $httpUrl = str_replace('wss://', 'https://', $url);

        try {
            // Build JWT for API auth
            $header = $this->base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
            $now = time();
            $claims = [
                'iss'   => $apiKey,
                'iat'   => $now,
                'nbf'   => $now,
                'exp'   => $now + 60,
                'video' => ['roomList' => true],
            ];
            $payload   = $this->base64url(json_encode($claims));
            $signature = $this->base64url(hash_hmac('sha256', "$header.$payload", $apiSecret, true));
            $jwt       = "$header.$payload.$signature";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $jwt,
            ])->timeout(5)->post("$httpUrl/twirp/livekit.RoomService/ListRooms", []);

            $rooms = $response->json('rooms', []);

            return response()->json(['rooms' => $rooms]);
        } catch (\Exception $e) {
            return response()->json(['rooms' => [], 'error' => $e->getMessage()]);
        }
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

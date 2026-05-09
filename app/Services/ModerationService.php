<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ModerationService
{
    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.openai.key', '');
    }

    /**
     * Check text content using OpenAI Moderation API (free endpoint).
     * Returns ['flagged' => bool, 'categories' => [...]] or null on failure.
     */
    public function check(string $text): ?array
    {
        if (empty($this->apiKey) || empty(trim($text))) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ])->timeout(5)->post('https://api.openai.com/v1/moderations', [
                'input' => $text,
            ]);

            if (!$response->successful()) {
                Log::warning('OpenAI Moderation API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            $result = $response->json('results.0');

            return [
                'flagged'    => $result['flagged'] ?? false,
                'categories' => array_keys(array_filter($result['categories'] ?? [])),
            ];
        } catch (\Exception $e) {
            Log::warning('OpenAI Moderation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Returns true if content is safe (not flagged or API unavailable).
     * Fails open — if API is down, content is allowed through.
     */
    public function isSafe(string $text): bool
    {
        $result = $this->check($text);
        return $result === null || !$result['flagged'];
    }

    /**
     * Returns flagged categories or empty array.
     */
    public function flaggedCategories(string $text): array
    {
        $result = $this->check($text);
        return $result['categories'] ?? [];
    }
}

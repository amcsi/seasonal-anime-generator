<?php

declare(strict_types=1);

namespace App\Mal;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Minimal client for the official MyAnimeList API v2. Returns the decoded JSON as arrays.
 *
 * @see https://myanimelist.net/apiconfig/references/api/v2
 */
class MalClient
{
    private const BASE_URL = 'https://api.myanimelist.net/v2';

    public function __construct(private readonly string $clientId) {}

    /**
     * Includes shows carried over from earlier seasons; check `start_season` to exclude them.
     *
     * @param  string[]  $fields
     * @return array<int, array<string, mixed>> anime nodes
     */
    public function getSeason(int $year, string $season, array $fields): array
    {
        $nodes = [];
        $url = "anime/season/$year/$season";
        $query = ['limit' => 500, 'fields' => implode(',', $fields)];
        do {
            $response = $this->get($url, $query)->throw()->json();
            foreach ($response['data'] as $item) {
                $nodes[] = $item['node'];
            }
            // The next URL already carries the query.
            $url = $response['paging']['next'] ?? null;
            $query = [];
        } while ($url);

        return $nodes;
    }

    /**
     * @param  string[]  $fields
     * @return array<string, mixed>|null null if the anime does not exist
     */
    public function getAnime(int $id, array $fields): ?array
    {
        $response = $this->get("anime/$id", ['fields' => implode(',', $fields)]);
        if ($response->notFound()) {
            return null;
        }

        return $response->throw()->json();
    }

    private function get(string $url, array $query): Response
    {
        // Passing even an empty query would make Guzzle drop the query already in $url.
        return $query ? $this->request()->get($url, $query) : $this->request()->get($url);
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->withHeaders(['X-MAL-CLIENT-ID' => $this->clientId])
            ->connectTimeout(10)
            ->timeout(30)
            ->retry(
                3,
                2000,
                static fn ($e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->serverError()),
                throw: false
            );
    }
}

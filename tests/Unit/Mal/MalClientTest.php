<?php

declare(strict_types=1);

namespace Tests\Unit\Mal;

use App\Mal\MalClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MalClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_get_season_follows_pagination(): void
    {
        $next = 'https://api.myanimelist.net/v2/anime/season/2026/fall?offset=1&limit=1&fields=media_type';
        Http::fake(fn (Request $request) => match ($request->url()) {
            'https://api.myanimelist.net/v2/anime/season/2026/fall?limit=500&fields=media_type' => Http::response([
                'data' => [['node' => ['id' => 1, 'title' => 'First']]],
                'paging' => ['next' => $next],
            ]),
            $next => Http::response([
                'data' => [['node' => ['id' => 2, 'title' => 'Second']]],
                'paging' => [],
            ]),
        });

        $anime = new MalClient('test-client-id')->getSeason(2026, 'fall', ['media_type']);

        self::assertSame([1, 2], array_column($anime, 'id'));
        Http::assertSent(fn (Request $request) => $request->hasHeader('X-MAL-CLIENT-ID', 'test-client-id'));
    }

    public function test_get_anime(): void
    {
        Http::fake(fn (Request $request) => match ($request->url()) {
            'https://api.myanimelist.net/v2/anime/5?fields=synopsis%2Crelated_anime' => Http::response([
                'id' => 5,
                'synopsis' => 'Hello',
            ]),
        });

        $anime = new MalClient('test-client-id')->getAnime(5, ['synopsis', 'related_anime']);

        self::assertSame('Hello', $anime['synopsis']);
    }

    public function test_get_anime_not_found(): void
    {
        Http::fake(['api.myanimelist.net/v2/anime/5*' => Http::response(['error' => 'not_found'], 404)]);

        self::assertNull(new MalClient('test-client-id')->getAnime(5, ['start_date']));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Extractor;

use App\Extractor\AnimeExtractor;
use App\Extractor\BasicAnimeExtractor;
use Tests\TestCase;

class AnimeExtractorTest extends TestCase
{
    public function test_extract_titles(): void
    {
        $anime = [
            'title' => 'Kaoru Hana wa Rin to Saku',
            'alternative_titles' => ['en' => 'The Fragrant Flower Blooms with Dignity'],
        ];

        $instance = new AnimeExtractor($anime, []);

        self::assertSame(
            "Kaoru Hana wa Rin to Saku\nThe Fragrant Flower Blooms with Dignity",
            $instance->extractTitles()
        );
    }

    public function test_extract_titles_same_in_both_languages(): void
    {
        $anime = [
            'title' => 'Sanda',
            'alternative_titles' => ['en' => 'Sanda'],
        ];

        $instance = new AnimeExtractor($anime, []);

        self::assertSame(
            'Sanda',
            $instance->extractTitles()
        );
    }

    public function test_extract_titles_empty_english(): void
    {
        $anime = [
            'title' => 'Liar Game',
            'alternative_titles' => ['en' => ''],
        ];

        $instance = new AnimeExtractor($anime, []);

        self::assertSame('Liar Game', $instance->extractTitles());
    }

    public function test_extract_start_date(): void
    {
        $instance = new AnimeExtractor(['start_date' => '2025-10-12'], []);

        self::assertSame('2025-10-12', $instance->extractStartDate());
    }

    public function test_extract_start_date_partial(): void
    {
        self::assertSame('2026-12-01', new AnimeExtractor(['start_date' => '2026-12'], [])->extractStartDate());
        self::assertSame('2026-01-01', new AnimeExtractor(['start_date' => '2026'], [])->extractStartDate());
        self::assertNull(new AnimeExtractor([], [])->extractStartDate());
    }

    public function test_normalize_date(): void
    {
        self::assertSame('2026-12-01', BasicAnimeExtractor::normalizeDate('2026-12'));
        self::assertNull(BasicAnimeExtractor::normalizeDate(''));
        self::assertNull(BasicAnimeExtractor::normalizeDate(null));
    }

    public function test_extract_image(): void
    {
        $imageUrl = 'https://cdn.myanimelist.net/images/anime/1168/148347.jpg';

        $instance = new AnimeExtractor([
            'main_picture' => [
                'medium' => $imageUrl,
                'large' => 'https://cdn.myanimelist.net/images/anime/1168/148347l.jpg',
            ],
        ], []);

        self::assertSame($imageUrl, $instance->extractImage());
    }

    public function test_extract_image_webp_as_jpg(): void
    {
        $instance = new AnimeExtractor([
            'main_picture' => ['medium' => 'https://cdn.myanimelist.net/images/anime/1244/138851.webp'],
        ], []);

        self::assertSame('https://cdn.myanimelist.net/images/anime/1244/138851.jpg', $instance->extractImage());
    }

    public function test_extract_image_missing(): void
    {
        self::assertNull(new AnimeExtractor([], [])->extractImage());
    }

    public function test_extract_genres(): void
    {
        $instance = new AnimeExtractor([
            'genres' => [
                ['id' => 14, 'name' => 'Horror'],
                ['id' => 7, 'name' => 'Mystery'],
                ['id' => 37, 'name' => 'Supernatural'],
            ],
        ], []);

        self::assertSame(['Horror', 'Mystery', 'Supernatural'], $instance->extractGenres());
    }

    public function test_extract_popularity(): void
    {
        self::assertSame(189583, new AnimeExtractor(['num_list_users' => 189583], [])->extractPopularity());
    }

    public function test_extract_trailer(): void
    {
        $instance = new AnimeExtractor([
            'title' => 'Kaoru Hana wa Rin to Saku',
            'alternative_titles' => ['en' => 'The Fragrant Flower Blooms with Dignity'],
        ], []);

        self::assertSame(
            'https://www.youtube.com/results?search_query='.urlencode('The Fragrant Flower Blooms with Dignity'),
            $instance->extractTrailer()
        );
    }

    public function test_extract_trailer_no_english(): void
    {
        $instance = new AnimeExtractor(['title' => 'Liar Game', 'alternative_titles' => ['en' => '']], []);

        self::assertSame(
            'https://www.youtube.com/results?search_query='.urlencode('Liar Game'),
            $instance->extractTrailer()
        );
    }

    public function test_extract_synopsis(): void
    {
        $synopsis = "hey\n\nyo\n\n[Written by MAL Rewrite]\n\n(Source: Alpha Manga)";

        $instance = new AnimeExtractor([], ['synopsis' => $synopsis]);

        self::assertSame("hey\nyo", $instance->extractSynopsis());
    }
}

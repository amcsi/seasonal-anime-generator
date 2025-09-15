<?php

declare(strict_types=1);

namespace Tests\Unit\Extractor;

use App\Extractor\AnimeExtractor;
use Jikan\JikanPHP\Model\Anime;
use Jikan\JikanPHP\Model\AnimeFull;
use Jikan\JikanPHP\Model\AnimeImages;
use Jikan\JikanPHP\Model\AnimeImagesJpg;
use Jikan\JikanPHP\Model\Daterange;
use Jikan\JikanPHP\Model\MalUrl;
use Jikan\JikanPHP\Model\Title;
use Jikan\JikanPHP\Model\TrailerBase;
use Tests\TestCase;

class AnimeExtractorTest extends TestCase
{
    public function test_extract_titles(): void
    {
        $anime = new Anime;
        $japaneseTitle = new Title;
        $japaneseTitle->setTitle('Kaoru Hana wa Rin to Saku');
        $japaneseTitle->setType('Default');
        $englishTitle = new Title;
        $englishTitle->setTitle('The Fragrant Flower Blooms with Dignity');
        $englishTitle->setType('English');

        $anime->setTitles([$japaneseTitle, $englishTitle]);

        $instance = new AnimeExtractor($anime, new AnimeFull);

        self::assertSame(
            "Kaoru Hana wa Rin to Saku\nThe Fragrant Flower Blooms with Dignity",
            $instance->extractTitles()
        );
    }

    public function test_extract_start_date(): void
    {
        $anime = new Anime;
        $dateRange = new Daterange;
        $from = '2025-10-12T00:00:00+00:00';
        $dateRange->setFrom($from);
        $anime->setAired($dateRange);

        $instance = new AnimeExtractor($anime, new AnimeFull);

        self::assertSame('2025-10-12', $instance->extractStartDate());
    }

    public function test_extract_image(): void
    {
        $imageUrl = 'https://cdn.myanimelist.net/images/anime/1168/148347.jpg';

        $anime = new Anime;
        $images = new AnimeImages;
        $jpg = new AnimeImagesJpg;
        $jpg->setImageUrl($imageUrl);
        $images->setJpg($jpg);
        $anime->setImages($images);

        $instance = new AnimeExtractor($anime, new AnimeFull);

        self::assertSame($imageUrl, $instance->extractImage());
    }

    public function test_extract_genres(): void
    {
        $anime = new Anime;
        $genresArray = ['Horror', 'Mystery', 'Supernatural'];
        $genres = [];
        foreach ($genresArray as $item) {
            $genre = new MalUrl;
            $genre->setName($item);
            $genres[] = $genre;
        }
        $anime->setGenres($genres);

        $instance = new AnimeExtractor($anime, new AnimeFull);

        self::assertSame('Horror, Mystery, Supernatural', $instance->extractGenres());
    }

    public function test_extract_trailer(): void
    {
        $anime = new Anime;
        $url = 'https://www.youtube.com/watch?v=j1hqIrLqOso';
        $trailer = new TrailerBase;
        $trailer->setUrl($url);
        $anime->setTrailer($trailer);

        $instance = new AnimeExtractor($anime, new AnimeFull);

        self::assertSame('https://www.youtube.com/watch?v=j1hqIrLqOso', $instance->extractTrailer());
    }

    public function test_extract_synopsis(): void
    {
        $anime = new AnimeFull;
        $synopsis = "hey\n\nyo\n\n[Written by MAL Rewrite]\n\n(Source: Alpha Manga)";
        $anime->setSynopsis($synopsis);

        $instance = new AnimeExtractor(new Anime, $anime);

        self::assertSame("hey\nyo", $instance->extractSynopsis());
    }
}

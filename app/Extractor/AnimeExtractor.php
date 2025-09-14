<?php

declare(strict_types=1);

namespace App\Extractor;

use Illuminate\Support\Arr;
use Jikan\JikanPHP\Model\Anime;
use Jikan\JikanPHP\Model\AnimeFull;
use Jikan\JikanPHP\Model\Genre;

class AnimeExtractor
{
    public function __construct(private Anime $anime, private AnimeFull $animeFull) {}

    public function extractTitles(): string
    {
        $titles = $this->anime->getTitles();

        $original = '';
        $english = '';

        foreach ($titles as $title) {
            if ($title->getType() === 'Default') {
                $original = $title->getTitle();
            } elseif ($title->getType() === 'English') {
                $english = $title->getTitle();
            }
        }

        return trim("$original\n$english");
    }

    public function extractImage(): string {}

    public function extractStartDate(): string
    {
        $aired = $this->anime->getAired();

        return substr($aired->getFrom(), 0, 10);
    }

    public function extractGenres(): string
    {
        return implode(', ', Arr::map($this->anime->getGenres(), fn (Genre $genre) => $genre->getName()));
    }

    public function extractTrailer(): string
    {
        return $this->anime->getTrailer()->getUrl();
    }

    public function extractSynopsis(): string
    {
        return trim(preg_replace(
            "/\n{2,}/",
            "\n",
            preg_replace('/^\[Written by.+]$/m', '', $this->animeFull->getSynopsis())
        ));
    }
}

<?php

declare(strict_types=1);

namespace App\Extractor;

use Illuminate\Support\Arr;
use JetBrains\PhpStorm\Immutable;
use Jikan\JikanPHP\Model\Anime;
use Jikan\JikanPHP\Model\AnimeFull;
use Jikan\JikanPHP\Model\Genre;

#[Immutable]
class AnimeExtractor
{
    public function __construct(public Anime $anime, public AnimeFull $animeFull) {}

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

    public function extractPopularity(): ?int
    {
        return $this->anime->getMembers();
    }

    public function extractTrailer(): ?string
    {
        return $this->anime->getTrailer()->getUrl();
    }

    public function extractSynopsis(): ?string
    {
        $synopsis = $this->animeFull->getSynopsis();

        return $synopsis ? trim(preg_replace(
            "/\n{2,}/",
            "\n",
            preg_replace('/^\[Written by.+]$/m', '', $synopsis)
        )) : null;
    }
}

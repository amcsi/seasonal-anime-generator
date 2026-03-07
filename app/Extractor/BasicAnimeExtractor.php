<?php

declare(strict_types=1);

namespace App\Extractor;

use Illuminate\Support\Arr;
use Jikan\JikanPHP\Model\Anime;
use Jikan\JikanPHP\Model\MalUrl;

class BasicAnimeExtractor
{
    public function __construct(public Anime $anime) {}

    public function extractTitlesAsArray(): array
    {
        $titles = $this->anime->getTitles();

        $titlesToReturn = [
            0 => null, // Original
            1 => null, // English
        ];

        foreach ($titles as $title) {
            if ($title->getType() === 'Default') {
                $titlesToReturn[0] = $title->getTitle();
            } elseif ($title->getType() === 'English') {
                $titlesToReturn[1] = $title->getTitle();
            }
        }

        return $titlesToReturn;
    }

    public function extractImage(): ?string
    {
        return $this->anime->getImages()->getJpg()->getImageUrl();
    }

    public function extractGenres(): array
    {
        return Arr::map($this->anime->getGenres(), fn (MalUrl $genre) => $genre->getName());
    }

    public function extractTitles(): string
    {
        return trim(implode("\n", array_unique($this->extractTitlesAsArray())));
    }

    public function extractStartDate(): string
    {
        $aired = $this->anime->getAired();

        return substr($aired->getFrom(), 0, 10);
    }

    public function extractPopularity(): ?int
    {
        return $this->anime->getMembers();
    }

    public function extractTrailer(): ?string
    {
        $title = $this->extractTitlesAsArray();
        $titleString = end($title);

        return $titleString ? 'https://www.youtube.com/results?search_query='.urlencode($titleString) : '';
    }
}

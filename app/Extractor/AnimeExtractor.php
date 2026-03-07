<?php

declare(strict_types=1);

namespace App\Extractor;

use JetBrains\PhpStorm\Immutable;
use Jikan\JikanPHP\Model\Anime;
use Jikan\JikanPHP\Model\AnimeFull;

#[Immutable]
class AnimeExtractor extends BasicAnimeExtractor
{
    public function __construct(Anime $anime, public AnimeFull $animeFull)
    {
        parent::__construct($anime);
    }

    public function extractSynopsis(): ?string
    {
        $synopsis = $this->animeFull->getSynopsis();

        return $synopsis ? trim(preg_replace(
            "/\n{2,}/",
            "\n",
            preg_replace(['/^\[Written by.+]$/m', '/^\(Source:.+\)$/m'], '', $synopsis)
        )) : null;
    }
}

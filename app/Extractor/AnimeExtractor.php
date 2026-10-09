<?php

declare(strict_types=1);

namespace App\Extractor;

use JetBrains\PhpStorm\Immutable;

#[Immutable]
class AnimeExtractor extends BasicAnimeExtractor
{
    /**
     * @param  array<string, mixed>  $anime  MAL API anime node from the season listing
     * @param  array<string, mixed>  $details  MAL API anime details
     */
    public function __construct(array $anime, public array $details)
    {
        parent::__construct($anime);
    }

    public function extractSynopsis(): ?string
    {
        $synopsis = $this->details['synopsis'] ?? null;

        return $synopsis ? trim(preg_replace(
            "/\n{2,}/",
            "\n",
            preg_replace(['/^\[Written by.+]$/m', '/^\(Source:.+\)$/m'], '', $synopsis)
        )) : null;
    }
}

<?php

declare(strict_types=1);

namespace App\Extractor;

class BasicAnimeExtractor
{
    /**
     * @param  array<string, mixed>  $anime  MAL API anime node
     */
    public function __construct(public array $anime) {}

    public function extractTitlesAsArray(): array
    {
        return [
            0 => $this->anime['title'] ?? null, // Original
            1 => ($this->anime['alternative_titles']['en'] ?? null) ?: null, // English
        ];
    }

    public function extractTitlePreferringEnglish()
    {
        $titlesArray = $this->extractTitlesAsArray();

        return $titlesArray[1] ?? $titlesArray[0];
    }

    public function extractImage(): ?string
    {
        $url = $this->anime['main_picture']['medium'] ?? null;

        // A few pictures are WebP, which PhpSpreadsheet can't embed; the CDN serves the same image as JPEG.
        return $url ? preg_replace('/\.webp$/', '.jpg', $url) : null;
    }

    public function extractGenres(): array
    {
        return array_column($this->anime['genres'] ?? [], 'name');
    }

    public function extractTitles(): string
    {
        return trim(implode("\n", array_unique(array_filter($this->extractTitlesAsArray()))));
    }

    public function extractStartDate(): ?string
    {
        return self::normalizeDate($this->anime['start_date'] ?? null);
    }

    public function extractPopularity(): ?int
    {
        return $this->anime['num_list_users'] ?? null;
    }

    public function extractTrailer(): ?string
    {
        $titleString = $this->extractTitlePreferringEnglish();

        return $titleString ? 'https://www.youtube.com/results?search_query='.urlencode($titleString) : '';
    }

    /**
     * MAL dates may be partial ("2026" or "2026-12"); pad them to a full Y-m-d date.
     */
    public static function normalizeDate(?string $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        return match (strlen($date)) {
            4 => "$date-01-01",
            7 => "$date-01",
            default => $date,
        };
    }
}

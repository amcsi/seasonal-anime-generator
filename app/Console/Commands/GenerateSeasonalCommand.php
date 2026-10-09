<?php

namespace App\Console\Commands;

use App\Extractor\AnimeExtractor;
use App\Extractor\BasicAnimeExtractor;
use App\Http\Cache\HttpCacher;
use App\Mal\MalClient;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GenerateSeasonalCommand extends Command
{
    protected $signature = 'app:generate-seasonal';

    protected $description = 'Command description';

    public function handle(): void
    {
        $mal = app(MalClient::class);
        $now = now();
        $seasonStart = $now->subMonth()->addQuarter()->floorQuarters();
        $year = $seasonStart->year;
        $season = match ($seasonStart->quarter) {
            1 => 'winter',
            2 => 'spring',
            3 => 'summer',
            4 => 'fall',
        };

        $this->info("Year: $year; Season: $season");
        $dateFormatted = now()->format('Ymd_His');
        $filename = "seasonal_{$year}_{$season}_{$dateFormatted}.xlsx";

        /** @var Repository $cache */
        $cache = Cache::driver('file');

        $seasonalAnime = $cache->remember(
            "mal-season-$year-$season",
            now()->addHours(8),
            fn () => $mal->getSeason($year, $season, [
                'alternative_titles',
                'start_date',
                'start_season',
                'genres',
                'media_type',
                'num_list_users',
                'main_picture',
            ])
        );
        // MAL also lists shows still airing from earlier seasons.
        $seasonalAnime = array_filter(
            $seasonalAnime,
            fn (array $anime) => ($anime['start_season']['year'] ?? null) === $year
                && ($anime['start_season']['season'] ?? null) === $season
        );
        $seasonalAnime = collect($seasonalAnime)->unique('id')->values()->all();
        $seasonalAnime = Arr::sortDesc($seasonalAnime, fn (array $anime) => $anime['num_list_users'] ?? 0);

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial');
        $spreadsheet->getDefaultStyle()->getFont()->setSize(10);
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle('Anime');

        $linkColor = new Color()->bindParent($spreadsheet)->setHyperlinkTheme();

        $httpCacher = app(HttpCacher::class);

        $imageWidth = 120;
        $configuration = [
            'Name (Japanese, English)' => [function ($cell, AnimeExtractor $extractor) use ($linkColor, $worksheet) {
                $id = $extractor->anime['id'];
                $worksheet->setCellValue($cell, $extractor->extractTitles());
                $worksheet->getCell($cell)->getHyperlink()->setUrl("https://myanimelist.net/anime/$id");
                $worksheet->getCell($cell)->getStyle()->getFont()->setColor($linkColor);
                $worksheet->getCell($cell)->getStyle()->getAlignment()->setWrapText(true);
            }, 292],
            'Image' => [function ($cell, AnimeExtractor $extractor) use ($worksheet, $imageWidth, $httpCacher) {
                $image = $extractor->extractImage();
                if (! $image) {
                    return;
                }
                $drawing = new Drawing;
                $malId = $extractor->anime['id'];

                $imagePath = $httpCacher->getLocalPath(
                    $image,
                    "images/$malId.jpg",
                    Storage::drive('mal'),
                    CarbonImmutable::now()->subWeek()
                );
                $drawing->setPath($imagePath);
                $drawing->setWidth($imageWidth + 10);
                $drawing->setCoordinates($cell);
                $drawing->setWorksheet($worksheet);
            }, $imageWidth],
            'Start date' => [function ($cell, AnimeExtractor $extractor) use ($worksheet) {
                $startDateString = $extractor->extractStartDate();
                if (! $startDateString) {
                    return;
                }
                $worksheet->setCellValue($cell, Date::convertIsoDate($startDateString));
                $worksheet->getStyle($cell)->getNumberFormat()->setFormatCode('mmm d');
            }, 80],
            'Genres' => [function ($cell, AnimeExtractor $extractor) use ($worksheet) {
                $worksheet->setCellValue($cell, implode(",\n", $extractor->extractGenres()));
                $worksheet->getCell($cell)->getStyle()->getAlignment()->setWrapText(true);
            }, 100],
            'Popularity' => [function ($cell, AnimeExtractor $extractor) use ($worksheet) {
                $worksheet->setCellValue($cell, $extractor->extractPopularity());
            }, 68],
            'Trailer/PV' => function ($cell, AnimeExtractor $extractor) use ($linkColor, $worksheet) {
                $url = $extractor->extractTrailer();
                if (! $url) {
                    return;
                }
                $worksheet->setCellValue($cell, 'Link');
                $worksheet->getCell($cell)->getHyperlink()->setUrl($url);
                $worksheet->getCell($cell)->getStyle()->getFont()->setColor($linkColor);
                $worksheet->getCell($cell)->getStyle()->getAlignment()->setHorizontal('center');
            },
            'TL;DR' => [function ($cell, AnimeExtractor $extractor) use ($worksheet) {
                $worksheet->getCell($cell)->getStyle()->getAlignment()->setWrapText(true);
            }, 144],
            'Synopsis' => [function ($cell, AnimeExtractor $extractor) use ($worksheet) {
                $worksheet->setCellValue($cell, $extractor->extractSynopsis());
                $worksheet->getCell($cell)->getStyle()->getAlignment()->setWrapText(true);
            }, 711],
            'MAL ID' => [function ($cell, AnimeExtractor $extractor) use ($worksheet) {
                $worksheet->setCellValue($cell, $extractor->anime['id']);
            }],
        ];

        $worksheet->fromArray(array_keys($configuration));
        $column = 'A';
        foreach ($configuration as $config) {
            $config = Arr::wrap($config);
            $width = $config[1] ?? null;
            if ($width) {
                $worksheet->getColumnDimension($column)->setWidth($width, 'px');
            }
            $column++;
        }

        $additionalSkip = Arr::map(config('core.ignore_mal_ids'), static fn ($malId) => (int) $malId);

        $row = 2;
        foreach ($seasonalAnime as $anime) {
            $malId = $anime['id'];
            $basicAnimeExtractor = new BasicAnimeExtractor($anime);
            $animeTitle = $basicAnimeExtractor->extractTitlePreferringEnglish();
            $this->line("Anime: {$animeTitle}");
            $type = $anime['media_type'] ?? null;
            if (! in_array($type, ['tv', 'ova', 'ona'], true)) {
                $this->warn("Skipping type: {$type}");

                continue;
            }
            if (in_array($malId, $additionalSkip, true)) {
                $this->warn('Skipping due to skip list.');

                continue;
            }
            $genres = $basicAnimeExtractor->extractGenres();
            if (in_array('Hentai', $genres, true)) {
                $this->warn('Skipping due to Hentai');

                continue;
            }
            try {
                $animeDetails = $cache->remember(
                    "mal-anime-details-$malId",
                    now()->addHours(8),
                    function () use ($mal, $malId) {
                        $animeDetails = $mal->getAnime($malId, ['synopsis', 'related_anime']);
                        sleep(1); // Throttle.
                        if (! $animeDetails) {
                            throw new \RuntimeException("Anime $malId not found");
                        }

                        return $animeDetails;
                    }
                );
            } catch (\Throwable $e) {
                \Log::warning($e);
                $this->warn($e);

                continue;
            }
            $relations = $animeDetails['related_anime'] ?? [];
            $today = now()->startOfDay();
            foreach ($relations as $relation) {
                $relationType = $relation['relation_type_formatted'];
                if (! in_array($relation['relation_type'], ['prequel', 'sequel'], true)) {
                    continue;
                }
                $relatedMalId = $relation['node']['id'];
                $relatedAnime = $cache->remember(
                    "mal-anime-start-date-$relatedMalId",
                    now()->addHours(8),
                    function () use ($mal, $relatedMalId) {
                        $relatedAnime = $mal->getAnime($relatedMalId, ['start_date']);
                        sleep(1); // Throttle.

                        // Cache "not found" too, as remember() would not cache null.
                        return $relatedAnime ?? [];
                    }
                );
                $from = BasicAnimeExtractor::normalizeDate($relatedAnime['start_date'] ?? null);
                if ($from === null) {
                    continue;
                }
                $startDate = CarbonImmutable::parse($from);
                if ($startDate->lt($today)) {
                    $this->warn("Skipping due to {$relationType} (MAL ID {$relatedMalId}) that started in the past.");

                    continue 2;
                }
            }
            $column = 'A';
            foreach ($configuration as $callback) {
                $callback = Arr::wrap($callback)[0];

                $worksheet->getRowDimension($row)->setRowHeight(200, 'px');
                $extractor = new AnimeExtractor($anime, $animeDetails);
                $callback("$column$row", $extractor);

                $column++;
            }
            $this->info('Success');
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save(storage_path('app/private/'.$filename));
    }
}

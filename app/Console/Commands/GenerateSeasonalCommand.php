<?php

namespace App\Console\Commands;

use App\Extractor\AnimeExtractor;
use App\Extractor\BasicAnimeExtractor;
use App\Http\Cache\HttpCacher;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Jikan\JikanPHP\Client;
use Jikan\JikanPHP\Model\Anime;
use Jikan\JikanPHP\Model\AnimeFull;
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
        $jikan = app(Client::class);
        $now = now();
        $seasonStart = $now->addQuarter()->floorQuarters();
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

        $pages = [];
        $page = 0;
        do {
            $page++;
            $seasonResponse = $jikan->getSeason($year, $season, ['page' => $page]);
            $pages[] = $seasonResponse->getData();
        } while ($seasonResponse->getPagination()->getHasNextPage());

        $seasonalAnime = Arr::flatten($pages, 1);
        /** @var Anime[] $seasonalAnime */
        $seasonalAnime = Arr::sortDesc($seasonalAnime, fn (Anime $anime) => $anime->getMembers());

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial');
        $spreadsheet->getDefaultStyle()->getFont()->setSize(10);
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle('Anime');

        $noop = function () {};

        $linkColor = new Color()->bindParent($spreadsheet)->setHyperlinkTheme();

        /** @var Repository $cache */
        $cache = Cache::driver('file');
        $httpCacher = app(HttpCacher::class);

        $imageWidth = 120;
        $configuration = [
            'Name (Japanese, English)' => [function ($cell, AnimeExtractor $extractor) use ($linkColor, $worksheet) {
                $id = $extractor->anime->getMalId();
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
                $malId = $extractor->anime->getMalId();

                $imagePath = $httpCacher->getLocalPath(
                    $image,
                    "images/$malId.jpg",
                    Storage::drive('jikan'),
                    CarbonImmutable::now()->subWeek()
                );
                $drawing->setPath($imagePath);
                $drawing->setWidth($imageWidth + 10);
                $drawing->setCoordinates($cell);
                $drawing->setWorksheet($worksheet);
            }, $imageWidth],
            'Start date' => [function ($cell, AnimeExtractor $extractor) use ($worksheet) {
                $startDateString = $extractor->extractStartDate();
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
            'TL;DR' => [$noop, 144],
            'Synopsis' => [function ($cell, AnimeExtractor $extractor) use ($worksheet) {
                $worksheet->setCellValue($cell, $extractor->extractSynopsis());
                $worksheet->getCell($cell)->getStyle()->getAlignment()->setWrapText(true);
            }, 711],
            'MAL ID' => [function ($cell, AnimeExtractor $extractor) use ($worksheet) {
                $worksheet->setCellValue($cell, $extractor->anime->getMalId());
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
            $malId = $anime->getMalId();
            $basicAnimeExtractor = new BasicAnimeExtractor($anime);
            $animeTitle = array_first($basicAnimeExtractor->extractTitlesAsArray());
            $this->line("Anime: {$animeTitle}");
            if (! in_array($anime->getType(), ['TV', 'OVA', 'ONA'], true)) {
                $this->warn("Skipping type: {$anime->getType()}");

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
                /** @var AnimeFull $fullAnime */
                $fullAnime = $cache->remember(
                    "full-anime-$malId",
                    now()->addHours(8),
                    function () use ($jikan, $malId) {
                        $animeFull = $jikan->getAnimeFullById($malId);
                        sleep(1); // Throttle.
                        if (! $animeFull) {
                            throw new \RuntimeException("Anime $malId not found");
                        }

                        return $animeFull->getData();
                    }
                );
                $extractor = new AnimeExtractor($anime, $fullAnime);
            } catch (\Throwable $e) {
                \Log::warning($e);
                $this->warn($e);

                continue;
            }
            $column = 'A';
            foreach ($configuration as $callback) {
                $callback = Arr::wrap($callback)[0];

                $worksheet->getRowDimension($row)->setRowHeight(200, 'px');
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

<?php

namespace App\Console\Commands;

use App\Extractor\AnimeExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Jikan\JikanPHP\Client;
use Jikan\JikanPHP\Model\Anime;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GenerateSeasonalCommand extends Command
{
    protected $signature = 'app:generate-seasonal';

    protected $description = 'Command description';

    public function handle(): void
    {
        $jikan = app(Client::class);
        $year = 2025;
        $season = 'fall';

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

        $seasonalAnime = Arr::sortDesc($seasonalAnime, fn (Anime $anime) => $anime->getMembers());

        $spreadsheet = new Spreadsheet;
        $worksheet = $spreadsheet->getActiveSheet();

        $noop = function () {};

        $configuration = [
            'Name (Japanese, English)' => function ($cell, AnimeExtractor $extractor) use ($worksheet) {
                $worksheet->setCellValue($cell, $extractor->extractTitles());
            },
            'Image' => $noop,
            'Start date' => function ($cell, AnimeExtractor $extractor) use ($worksheet) {
                $worksheet->setCellValue($cell, $extractor->extractStartDate());
            },
            'Genres' => function ($cell, AnimeExtractor $extractor) {
                //                $worksheet->setCellValue($cell, $extractor->extractGenres());
            },
            'Popularity' => function ($cell, AnimeExtractor $extractor) use ($worksheet) {
                $worksheet->setCellValue($cell, $extractor->extractPopularity());
            },
            'Trailer/PV' => function ($cell, AnimeExtractor $extractor) use ($worksheet) {
                $worksheet->setCellValue($cell, $extractor->extractTrailer());
            },
            'TL;DR' => $noop,
            'Synopsis' => function ($cell, AnimeExtractor $extractor) use ($worksheet) {
                $worksheet->setCellValue($cell, $extractor->extractSynopsis());
            },
        ];

        $worksheet->fromArray(array_keys($configuration));

        $row = 2;
        foreach ($seasonalAnime as $anime) {
            $column = 'A';
            foreach ($configuration as $callback) {
                try {
                    $extractor = new AnimeExtractor($anime, $jikan->getAnimeFullById($anime->getMalId())->getData());
                } catch (\Throwable $e) {
                    \Log::warning($e->getMessage());

                    continue;
                }
                $callback("$column$row", $extractor);

                $column++;
            }
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save(storage_path('app/private/'.$filename));
    }
}

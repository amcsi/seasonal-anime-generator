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
        /** @var Anime[] $seasonalAnime */
        $seasonalAnime = Arr::sortDesc($seasonalAnime, fn (Anime $anime) => $anime->getMembers());

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial');
        $spreadsheet->getDefaultStyle()->getFont()->setSize(10);
        $worksheet = $spreadsheet->getActiveSheet();

        $noop = function () {};

        $configuration = [
            'Name (Japanese, English)' => [function ($cell, AnimeExtractor $extractor) use ($worksheet) {
                $id = $extractor->anime->getMalId();
                $worksheet->setCellValue($cell, $extractor->extractTitles());
                $worksheet->getCell($cell)->getHyperlink()->setUrl("https://myanimelist.net/anime/$id");
                $worksheet->getCell($cell)->getStyle()->getAlignment()->setWrapText(true);
            }, 292],
            'Image' => [$noop, 120],
            'Start date' => [function ($cell, AnimeExtractor $extractor) use ($worksheet) {
                $worksheet->setCellValue($cell, $extractor->extractStartDate());
            }, 80],
            'Genres' => [function ($cell, AnimeExtractor $extractor) {
                //                $worksheet->setCellValue($cell, $extractor->extractGenres());
            }, 100],
            'Popularity' => [function ($cell, AnimeExtractor $extractor) use ($worksheet) {
                $worksheet->setCellValue($cell, $extractor->extractPopularity());
            }, 68],
            'Trailer/PV' => function ($cell, AnimeExtractor $extractor) use ($worksheet) {
                $url = $extractor->extractTrailer();
                if (! $url) {
                    return;
                }
                $worksheet->setCellValue($cell, 'Link');
                $worksheet->getCell($cell)->getHyperlink()->setUrl($url);
                $worksheet->getCell($cell)->getStyle()->getAlignment()->setHorizontal('center');
            },
            'TL;DR' => [$noop, 144],
            'Synopsis' => [function ($cell, AnimeExtractor $extractor) use ($worksheet) {
                $worksheet->setCellValue($cell, $extractor->extractSynopsis());
                $worksheet->getCell($cell)->getStyle()->getAlignment()->setWrapText(true);
            }, 711],
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

        $row = 2;
        foreach ($seasonalAnime as $anime) {
            $column = 'A';
            foreach ($configuration as $callback) {
                $callback = Arr::wrap($callback)[0];
                if (! in_array($anime->getType(), ['TV', 'OVA', 'ONA'], true)) {
                    continue 2;
                }

                $worksheet->getRowDimension($row)->setRowHeight(164, 'px');
                try {
                    $extractor = new AnimeExtractor($anime, $jikan->getAnimeFullById($anime->getMalId())->getData());
                } catch (\Throwable $e) {
                    \Log::warning($e->getMessage());
                    $this->warn($e->getMessage());

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

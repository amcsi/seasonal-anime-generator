<?php

namespace App\Console\Commands;

use App\Extractor\AnimeExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Jikan\JikanPHP\Client;
use Jikan\JikanPHP\Model\Anime;
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

        $linkColor = new Color()->bindParent($spreadsheet)->setHyperlinkTheme();

        $imageWidth = 120;
        $configuration = [
            'Name (Japanese, English)' => [function ($cell, AnimeExtractor $extractor) use ($linkColor, $worksheet) {
                $id = $extractor->anime->getMalId();
                $worksheet->setCellValue($cell, $extractor->extractTitles());
                $worksheet->getCell($cell)->getHyperlink()->setUrl("https://myanimelist.net/anime/$id");
                $worksheet->getCell($cell)->getStyle()->getFont()->setColor($linkColor);
                $worksheet->getCell($cell)->getStyle()->getAlignment()->setWrapText(true);
            }, 292],
            'Image' => [function ($cell, AnimeExtractor $extractor) use ($worksheet, $imageWidth) {
                $image = $extractor->extractImage();
                if (! $image) {
                    return;
                }
                $drawing = new Drawing;
                $drawing->setPath($image);
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

                $worksheet->getRowDimension($row)->setRowHeight(200, 'px');
                try {
                    $animeFull = $jikan->getAnimeFullById($anime->getMalId())->getData();
                    $relations = $animeFull->getRelations();
                    if ($relations) {
                        foreach ($relations as $relation) {
                            if (in_array($relation->getRelation(), ['Prequel', 'Sequel'], true)) {
                                continue 3;
                            }
                        }
                    }
                    $extractor = new AnimeExtractor($anime, $animeFull);
                    $genres = $extractor->extractGenres();
                    if (in_array('Hentai', $genres, true)) {
                        continue 2;
                    }
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

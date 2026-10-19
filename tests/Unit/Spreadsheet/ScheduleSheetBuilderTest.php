<?php

declare(strict_types=1);

namespace Tests\Unit\Spreadsheet;

use App\Spreadsheet\ScheduleSheetBuilder;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class ScheduleSheetBuilderTest extends TestCase
{
    private Spreadsheet $spreadsheet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->spreadsheet = new Spreadsheet;
        $anime = $this->spreadsheet->getActiveSheet();
        $anime->setTitle('Anime');
        $anime->fromArray([
            ['Name (Japanese, English)'],
            ["Neko to Ryuu\nThe Cat and the Dragon"],
            ['Same Title'],
            ["Rakudai Kenja no Gakuin Musou\nFrom Overshadowed to Overpowered"],
        ]);
    }

    public function test_numbers_count_up_from_row_above(): void
    {
        $schedule = new ScheduleSheetBuilder('Anime')->build($this->spreadsheet);

        $first = ScheduleSheetBuilder::FIRST_ROW;
        self::assertSame(1, $schedule->getCell("A$first")->getCalculatedValue());
        self::assertSame(2, $schedule->getCell('A'.($first + 1))->getCalculatedValue());

        $schedule->setCellValue('A'.($first + 1), 'Extras');
        self::assertSame(1, $schedule->getCell('A'.($first + 2))->getCalculatedValue());
    }

    public function test_english_title_is_last_line_of_referenced_anime_row(): void
    {
        $schedule = new ScheduleSheetBuilder('Anime')->build($this->spreadsheet);

        $first = ScheduleSheetBuilder::FIRST_ROW;
        $schedule->setCellValue("C$first", 4);
        $schedule->setCellValue('C'.($first + 1), 2);
        $schedule->setCellValue('C'.($first + 2), 3);

        self::assertSame('From Overshadowed to Overpowered', $schedule->getCell("F$first")->getCalculatedValue());
        self::assertSame('The Cat and the Dragon', $schedule->getCell('F'.($first + 1))->getCalculatedValue());
        self::assertSame('Same Title', $schedule->getCell('F'.($first + 2))->getCalculatedValue());
        self::assertSame('', $schedule->getCell('F'.($first + 3))->getCalculatedValue());
        self::assertNull($schedule->getCell("D$first")->getValue());
        self::assertNull($schedule->getCell("E$first")->getValue());
    }

    public function test_start_times_chain_from_previous_row(): void
    {
        $schedule = new ScheduleSheetBuilder('Anime')->build($this->spreadsheet);

        $first = ScheduleSheetBuilder::FIRST_ROW;
        $schedule->setCellValue("G$first", 25 / (24 * 60));

        $startTimes = array_map(
            fn (int $row) => Date::excelToDateTimeObject($schedule->getCell("B$row")->getCalculatedValue())->format('H:i'),
            [$first, $first + 1, $first + 2]
        );
        self::assertSame(['18:20', '19:05', '19:45'], $startTimes);
    }
}

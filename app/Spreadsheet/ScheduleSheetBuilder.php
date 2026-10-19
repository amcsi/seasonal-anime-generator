<?php

declare(strict_types=1);

namespace App\Spreadsheet;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class ScheduleSheetBuilder
{
    public const TITLE = 'Schedule';

    public const FIRST_ROW = 4;

    public const ROW_COUNT = 10;

    // Relative R1C1 references keep pointing at the row above even after rows get rearranged.
    private const PREVIOUS_ROW = 'INDIRECT("R[-1]C", FALSE)';

    private const PREVIOUS_EPISODE_LENGTH = 'INDIRECT("R[-1]C[5]", FALSE)';

    public function __construct(private readonly string $animeSheetTitle) {}

    public function build(Spreadsheet $spreadsheet): Worksheet
    {
        $worksheet = $spreadsheet->createSheet();
        $worksheet->setTitle(self::TITLE);

        $worksheet->fromArray(['Sor', 'Egyszerűsített cím', 'oliva cím', 'Angol cím', 'Rész hossza'], null, 'C1');

        $settings = [
            'Start time' => 18 * 60 + 20,
            'Break time' => 20,
            'Default time per episode' => 20,
        ];
        $settingsRow = 1;
        foreach ($settings as $label => $minutes) {
            $worksheet->setCellValue("I$settingsRow", $label);
            $worksheet->setCellValue("J$settingsRow", $minutes / (24 * 60));
            $worksheet->getStyle("J$settingsRow")->getNumberFormat()->setFormatCode('h:mm');
            $settingsRow++;
        }

        $previous = self::PREVIOUS_ROW;
        $previousEpisodeLength = self::PREVIOUS_EPISODE_LENGTH;
        $lastRow = self::FIRST_ROW + self::ROW_COUNT - 1;
        for ($row = self::FIRST_ROW; $row <= $lastRow; $row++) {
            $worksheet->setCellValue("A$row", "=N($previous) + 1");
            $worksheet->setCellValue(
                "B$row",
                "=IF($previous <> \"\", $previous + IF($previousEpisodeLength <> \"\", $previousEpisodeLength, \$J\$3) + \$J\$2, \$J\$1)"
            );
            $worksheet->setCellValue("F$row", $this->englishTitleFormula("C$row"));
        }
        $worksheet->getStyle('B'.self::FIRST_ROW.":B$lastRow")->getNumberFormat()->setFormatCode('h:mm');

        $worksheet->getColumnDimension('C')->setWidth(4);
        $worksheet->getColumnDimension('D')->setWidth(29.88);
        $worksheet->getColumnDimension('E')->setWidth(29.88);
        $worksheet->getColumnDimension('F')->setWidth(22.25);
        $worksheet->getColumnDimension('I')->setWidth(22);

        return $worksheet;
    }

    /**
     * The Anime sheet's title cell holds the Japanese and English titles on separate lines; take the last line.
     */
    private function englishTitleFormula(string $animeRowCell): string
    {
        $titles = "INDEX('{$this->animeSheetTitle}'!\$A:\$A, $animeRowCell)";

        return "=IF($animeRowCell = \"\", \"\", TRIM(RIGHT(SUBSTITUTE($titles, CHAR(10), REPT(\" \", LEN($titles))), LEN($titles))))";
    }
}

<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Model\Report;

/**
 * Builds the CSV of the report. Cells that a spreadsheet would read as a formula (customer names and email
 * addresses come from visitors) get a leading apostrophe, so that they are shown as text.
 */
class CsvBuilder
{
    private const FORMULA_START = ['=', '+', '-', '@', "\t", "\r", "\n"];

    /**
     * Makes a cell safe to open in a spreadsheet.
     *
     * @param string $cell
     * @return string
     */
    public function protect(string $cell): string
    {
        return $cell !== '' && in_array($cell[0], self::FORMULA_START, true) ? "'" . $cell : $cell;
    }

    /**
     * One line of the CSV, with the line ending.
     *
     * @param string[] $cells
     * @return string
     */
    public function line(array $cells): string
    {
        $handle = fopen('php://memory', 'w+');
        if ($handle === false) {
            return '';
        }
        fputcsv($handle, array_map(fn ($cell) => $this->protect((string) $cell), $cells), ',', '"', '');
        rewind($handle);
        $line = (string) stream_get_contents($handle);
        fclose($handle);

        return $line;
    }
}

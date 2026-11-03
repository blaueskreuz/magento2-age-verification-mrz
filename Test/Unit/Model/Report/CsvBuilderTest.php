<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Test\Unit\Model\Report;

use BlauesKreuz\AgeVerificationMrz\Model\Report\CsvBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CsvBuilderTest extends TestCase
{
    private CsvBuilder $csv;

    protected function setUp(): void
    {
        $this->csv = new CsvBuilder();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function cellsProvider(): array
    {
        return [
            'equals' => ['=HYPERLINK("http://x")', '\'=HYPERLINK("http://x")'],
            'plus' => ['+41 79 000 00 00', "'+41 79 000 00 00"],
            'minus' => ['-1+1', "'-1+1"],
            'at' => ['@SUM(A1)', "'@SUM(A1)"],
            'tab' => ["\t=1", "'\t=1"],
            'carriage return' => ["\r=1", "'\r=1"],
            'plain text' => ['Anna Eriksson', 'Anna Eriksson'],
            'equals inside' => ['a=b', 'a=b'],
            'empty' => ['', ''],
            'number' => ['18', '18'],
        ];
    }

    #[DataProvider('cellsProvider')]
    public function testCellsThatLookLikeFormulasAreProtected(string $cell, string $expected): void
    {
        $this->assertSame($expected, $this->csv->protect($cell));
    }

    public function testLineIsQuotedAndEndsWithANewline(): void
    {
        $line = $this->csv->line(['000000001', 'Anna, "the" tester', '=1+1', '']);
        $this->assertSame("000000001,\"Anna, \"\"the\"\" tester\",'=1+1,\n", $line);
    }

    public function testLineWithALineBreakInACellStaysOneRecord(): void
    {
        $rows = str_getcsv($this->csv->line(["Anna\nBerta", 'x']), ',', '"', '');
        $this->assertSame(['Anna' . "\n" . 'Berta', 'x'], $rows);
    }
}

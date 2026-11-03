<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Test\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests the shipped translations (Swiss locales).
 */
class TranslationsTest extends TestCase
{
    private const LOCALES = ['de_CH', 'fr_CH', 'it_CH'];

    /** Texts that are the same in a language as in English. */
    private const SAME_AS_ENGLISH = [
        'it_CH' => ['Privacy:'],
    ];

    private const YOUTH = [
        'de_CH' => 'Gemeinsam für den Jugendschutz!',
        'fr_CH' => 'Ensemble pour la protection de la jeunesse !',
        'it_CH' => 'Insieme per la protezione dei giovani!',
    ];

    private const DONATION = [
        'de_CH' => "Perspektiven für's Leben – Ihre Spende für das Blaue Kreuz macht sie möglich: "
            . 'Unterstützen Sie unsere Suchtarbeit mit einer Spende – merci!',
        'fr_CH' => 'Des perspectives pour la vie – votre don à la Croix-Bleue les rend possibles : '
            . 'soutenez notre travail dans le domaine des addictions par un don – merci !',
        'it_CH' => 'Prospettive per la vita – la vostra donazione alla Croce Blu le rende possibili: '
            . 'sostenete il nostro lavoro nel campo delle dipendenze con una donazione – grazie!',
    ];

    private const DONATION_SOURCE = 'Perspectives for life – your donation to the Blue Cross makes them possible: '
        . 'support our addiction work with a donation – thank you!';

    /**
     * Reads a translation file.
     *
     * @return array<string, string>
     */
    private function read(string $locale): array
    {
        $handle = fopen(dirname(__DIR__, 2) . '/i18n/' . $locale . '.csv', 'r');
        $this->assertNotFalse($handle);
        $rows = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $this->assertCount(2, $row, 'Every line has a source and a translation.');
            $this->assertArrayNotHasKey($row[0], $rows, 'Duplicate source: ' . $row[0]);
            $rows[$row[0]] = $row[1];
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function localeProvider(): array
    {
        return array_combine(self::LOCALES, array_map(fn ($locale) => [$locale], self::LOCALES));
    }

    #[DataProvider('localeProvider')]
    public function testEveryLanguageHasTheSameStrings(string $locale): void
    {
        $this->assertNotEmpty($this->read($locale));
        $this->assertSame(array_keys($this->read('de_CH')), array_keys($this->read($locale)));
    }

    #[DataProvider('localeProvider')]
    public function testEveryStringIsTranslated(string $locale): void
    {
        foreach ($this->read($locale) as $source => $translation) {
            $this->assertNotSame('', trim($translation), 'Empty translation of: ' . $source);
            if (!in_array($source, self::SAME_AS_ENGLISH[$locale] ?? [], true)) {
                $this->assertNotSame($source, $translation, $locale . ': not translated: ' . $source);
            }
        }
    }

    #[DataProvider('localeProvider')]
    public function testPlaceholdersAndMarkupAreKept(string $locale): void
    {
        foreach ($this->read($locale) as $source => $translation) {
            foreach (['/%\d/', '/<\/?[a-z]+>/', '/&[a-z]+;/'] as $pattern) {
                preg_match_all($pattern, $source, $expected);
                preg_match_all($pattern, $translation, $actual);
                sort($expected[0]);
                sort($actual[0]);
                $this->assertSame($expected[0], $actual[0], $locale . ': ' . $pattern . ' differs in: ' . $source);
            }
        }
    }

    #[DataProvider('localeProvider')]
    public function testYouthProtectionMessageAndDonationCall(string $locale): void
    {
        $rows = $this->read($locale);
        $this->assertSame(self::YOUTH[$locale], $rows['Together for youth protection!']);
        $this->assertSame(self::DONATION[$locale], $rows[self::DONATION_SOURCE]);
    }
}

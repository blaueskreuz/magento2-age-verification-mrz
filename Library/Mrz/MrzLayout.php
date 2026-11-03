<?php

declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Library\Mrz;

/**
 * Describes a guided input for an MRZ and builds the MRZ from it.
 *
 * Instead of one long text, the customer fills in the blanks of a card that
 * looks like the document. The name line is not asked for, as it plays no part
 * in the check digits. Characters that are usually filler are pre-filled.
 */
final class MrzLayout {

  /**
   * Returns the blanks of a format, in reading order.
   *
   * @return \BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzSegment[]
   *   The segments.
   */
  public static function segments(MrzFormat $format): array {
    return match ($format) {
      // Line 1: document type, country, document number and its check digit,
      // then the optional data. Line 2: birth date, sex, expiry date and
      // nationality (each date with its check digit), optional data, and the
      // composite check digit.
      MrzFormat::TD1 => [
        new MrzSegment(0, 0, 15, FALSE),
        new MrzSegment(0, 15, 15, TRUE),
        new MrzSegment(1, 0, 18, FALSE),
        new MrzSegment(1, 18, 11, TRUE),
        new MrzSegment(1, 29, 1, FALSE),
      ],
      // Line 2: document number, nationality, birth date, sex and expiry date
      // with their check digits, optional data, and the composite check digit.
      MrzFormat::TD2 => [
        new MrzSegment(1, 0, 28, FALSE),
        new MrzSegment(1, 28, 7, TRUE),
        new MrzSegment(1, 35, 1, FALSE),
      ],
      // Line 2 as TD2, then the personal number, and the check digits of the
      // personal number and of the whole.
      MrzFormat::TD3 => [
        new MrzSegment(1, 0, 28, FALSE),
        new MrzSegment(1, 28, 14, TRUE),
        new MrzSegment(1, 42, 2, FALSE),
      ],
    };
  }

  /**
   * Returns the lines that are not asked for (the name lines).
   *
   * @return int[]
   *   The line numbers, starting at 0.
   */
  public static function ignoredLines(MrzFormat $format): array {
    return match ($format) {
      MrzFormat::TD1 => [2],
      MrzFormat::TD2, MrzFormat::TD3 => [0],
    };
  }

  /**
   * Builds the MRZ text from the entered segments.
   *
   * Entries are upper-cased and a space is read as "<". Filler segments that
   * are empty or short are completed with "<". Other segments are left as they
   * are, so that a missing character makes the validation fail instead of
   * being papered over. The lines that are not asked for become all filler.
   *
   * @param \BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzFormat $format
   *   The format.
   * @param string[] $values
   *   The entries, keyed by the position of the segment in ::segments().
   *   Missing entries use the segment's default.
   *
   * @return string
   *   The lines of the MRZ separated by line feeds.
   */
  public static function compose(MrzFormat $format, array $values): string {
    $lines = array_fill(0, $format->lineCount(), '');
    foreach (self::ignoredLines($format) as $line) {
      $lines[$line] = str_repeat('<', $format->lineLength());
    }
    foreach (self::segments($format) as $index => $segment) {
      $value = $values[$index] ?? $segment->defaultValue();
      $value = str_replace(' ', '<', mb_strtoupper((string) $value));
      if ($segment->filler) {
        $value = str_pad($value, $segment->length, '<');
      }
      $lines[$segment->line] .= $value;
    }
    return implode("\n", $lines);
  }

}

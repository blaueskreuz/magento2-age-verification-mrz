<?php

declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Library\Mrz;

/**
 * Normalises MRZ text, detects its format and extracts the fields.
 */
final class MrzParser {

  /**
   * Parses MRZ text as typed or pasted by a customer.
   *
   * Accepts any case, CR/LF line breaks, blank lines, and the lines run
   * together without line breaks. A space is read as the filler "<", because
   * that is what people type.
   *
   * @throws \BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzParseException
   */
  public function parse(string $input): MrzDocument {
    $lines = $this->normalise($input);
    foreach ($lines as $line) {
      if (!preg_match('/^[0-9A-Z<]*$/', $line)) {
        throw new MrzParseException(MrzError::InvalidCharacters);
      }
    }
    $format = $this->detectFormat($lines);
    return match ($format) {
      MrzFormat::TD1 => $this->parseTd1($lines),
      MrzFormat::TD2 => $this->parseTd2($lines),
      MrzFormat::TD3 => $this->parseTd3($lines),
    };
  }

  /**
   * Splits the input into upper-case lines.
   *
   * @return string[]
   *   The lines.
   */
  protected function normalise(string $input): array {
    $input = mb_strtoupper($input);
    $lines = [];
    foreach (preg_split('/\R/u', $input) ?: [] as $line) {
      if (trim($line) !== '') {
        $lines[] = str_replace(' ', '<', $line);
      }
    }
    // Lines run together: split by the total length.
    if (count($lines) === 1) {
      foreach (MrzFormat::cases() as $format) {
        if (mb_strlen($lines[0]) === $format->lineCount() * $format->lineLength()) {
          return mb_str_split($lines[0], max(1, $format->lineLength()));
        }
      }
    }
    return $lines;
  }

  /**
   * Detects the format from the number and length of the lines.
   *
   * @param string[] $lines
   *   The normalised lines.
   */
  protected function detectFormat(array $lines): MrzFormat {
    foreach (MrzFormat::cases() as $format) {
      if (count($lines) !== $format->lineCount()) {
        continue;
      }
      $lengths = array_unique(array_map('mb_strlen', $lines));
      if ($lengths === [$format->lineLength()]) {
        return $format;
      }
    }
    throw new MrzParseException(MrzError::UnknownFormat);
  }

  /**
   * Parses the three lines of 30 characters of a TD1 document.
   *
   * @param string[] $lines
   *   The normalised lines.
   */
  protected function parseTd1(array $lines): MrzDocument {
    [$one, $two] = $lines;
    return new MrzDocument(
      MrzFormat::TD1,
      substr($one, 5, 9),
      $one[14],
      substr($two, 0, 6),
      $two[6],
      substr($two, 8, 6),
      $two[14],
      NULL,
      NULL,
      substr($one, 5, 25) . substr($two, 0, 7) . substr($two, 8, 7) . substr($two, 18, 11),
      $two[29],
    );
  }

  /**
   * Parses the two lines of 36 characters of a TD2 document.
   *
   * @param string[] $lines
   *   The normalised lines.
   */
  protected function parseTd2(array $lines): MrzDocument {
    $two = $lines[1];
    return new MrzDocument(
      MrzFormat::TD2,
      substr($two, 0, 9),
      $two[9],
      substr($two, 13, 6),
      $two[19],
      substr($two, 21, 6),
      $two[27],
      NULL,
      NULL,
      substr($two, 0, 10) . substr($two, 13, 7) . substr($two, 21, 14),
      $two[35],
    );
  }

  /**
   * Parses the two lines of 44 characters of a TD3 document (passport).
   *
   * @param string[] $lines
   *   The normalised lines.
   */
  protected function parseTd3(array $lines): MrzDocument {
    $two = $lines[1];
    return new MrzDocument(
      MrzFormat::TD3,
      substr($two, 0, 9),
      $two[9],
      substr($two, 13, 6),
      $two[19],
      substr($two, 21, 6),
      $two[27],
      substr($two, 28, 14),
      $two[42],
      substr($two, 0, 10) . substr($two, 13, 7) . substr($two, 21, 22),
      $two[43],
    );
  }

}

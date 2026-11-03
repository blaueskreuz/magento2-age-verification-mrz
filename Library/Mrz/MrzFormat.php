<?php

declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Library\Mrz;

/**
 * The machine-readable zone formats defined by ICAO Doc 9303.
 */
enum MrzFormat: string {

  // Three lines of 30 characters (ID cards).
  case TD1 = 'TD1';

  // Two lines of 36 characters (some ID cards and visas).
  case TD2 = 'TD2';

  // Two lines of 44 characters (passports).
  case TD3 = 'TD3';

  /**
   * Returns the number of lines.
   */
  public function lineCount(): int {
    return match ($this) {
      self::TD1 => 3,
      self::TD2, self::TD3 => 2,
    };
  }

  /**
   * Returns the number of characters per line.
   */
  public function lineLength(): int {
    return match ($this) {
      self::TD1 => 30,
      self::TD2 => 36,
      self::TD3 => 44,
    };
  }

}

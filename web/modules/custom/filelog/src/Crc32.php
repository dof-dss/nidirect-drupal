<?php

namespace Drupal\filelog;

/**
 * CRC32 utility.
 *
 * Implements the crc32_combine() algorithm from ZLib.
 *
 * @see https://www.zlib.net/manual.html
 */
class Crc32 {

  /**
   * The current CRC hash.
   *
   * @var int
   */
  private int $crc1;

  /**
   * Start a new CRC32 hash.
   *
   * @param string $data
   *   The input string. Defaults to an empty string.
   */
  public function __construct(string $data = '') {
    $this->crc1 = crc32($data);
  }

  /**
   * Append data to the running CRC32 hash.
   *
   * This function, and ::matrixTimes() and ::matrixSquare(), reimplement the
   * algorithm from ZLib's CRC32 module.
   *
   * @param string $data
   *   The data to append.
   *
   * @return int
   *   The resulting CRC32.
   *
   * @see https://github.com/madler/zlib/blob/v1.2.11/crc32.c#L372
   */
  public function append(string $data): int {
    if (!$data) {
      return $this->crc1;
    }
    $crc2 = crc32($data);
    $len2 = strlen($data);

    $odd = [];
    $odd[0] = 0xedb88320;
    $row = 1;
    for ($n = 1; $n < 32; $n++) {
      $odd[$n] = $row;
      $row <<= 1;
    }

    $even = self::matrixSquare($odd);
    $odd = self::matrixSquare($even);
    do {
      $even = self::matrixSquare($odd);
      if ($len2 & 1) {
        $this->crc1 = self::matrixTimes($even, $this->crc1);
      }
      $len2 >>= 1;
      if (!$len2) {
        break;
      }
      $odd = self::matrixSquare($even);
      if ($len2 & 1) {
        $this->crc1 = self::matrixTimes($odd, $this->crc1);
      }
      $len2 >>= 1;
    } while ($len2);

    $this->crc1 ^= $crc2;
    return $this->crc1;
  }

  /**
   * Get current checksum.
   *
   * @return int
   *   Current checksum
   */
  public function get(): int {
    return $this->crc1;
  }

  /**
   * Multiply a bit matrix by itself.
   *
   * The matrix is represented as an array of 32bit integers.
   *
   * @param array $mat
   *   The matrix.
   *
   * @return array
   *   The result.
   */
  private static function matrixSquare(array $mat): array {
    $result = [];
    for ($i = 0; $i < 32; $i++) {
      $result[$i] = self::matrixTimes($mat, $mat[$i]);
    }
    return $result;
  }

  /**
   * Multiply a 32x32 bit matrix by a 32 bit vector.
   *
   * The bit vector is represented as a 32bit integer.
   *
   * PHP uses 32bit signed integers, but since we only use bit operations the
   * sign does not matter.
   *
   * @param int[] $mat
   *   The bit matrix, as an array of 32bit integers.
   * @param int $vec
   *   The bit vector, as a 32bit integer.
   *
   * @return int
   *   Output bit vector.
   */
  private static function matrixTimes(array $mat, int $vec): int {
    $sum = 0;
    for ($i = 0; $vec; $i++, $vec >>= 1) {
      if ($vec & 1) {
        $sum ^= $mat[$i];
      }
    }
    return $sum;
  }

}

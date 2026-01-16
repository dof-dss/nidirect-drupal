<?php

namespace Drupal\Tests\filelog\Unit;

use Drupal\filelog\Crc32;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the log rotation of the file logger.
 *
 * @group filelog
 */
class FileLogCrc32Test extends UnitTestCase {

  /**
   * Test the CRC32 utility class.
   */
  public function testCrc32(): void {
    $source = $this->randomMachineName(2000);
    $crc = new Crc32($source);

    for ($i = 0; $i < 10; $i++) {
      // Generate a random string that is anywhere between 1-2000 characters.
      $length = (($crc->get() % 1999) + 1999 + $i * 999983) % 1999 + 1;
      $append = $this->getRandomGenerator()->string($length);
      $source .= $append;
      $this->assertEquals(\crc32($source), $crc->append($append));
    }
  }

}

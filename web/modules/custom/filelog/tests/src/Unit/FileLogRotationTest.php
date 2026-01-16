<?php

namespace Drupal\Tests\filelog\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Utility\Token;
use Drupal\filelog\FileLogException;
use Drupal\filelog\LogFileManager;
use Drupal\filelog\LogFileManagerInterface;
use Drupal\filelog\LogRotator;
use function date;
use function date_default_timezone_set;
use function file_get_contents;
use function gzencode;
use function preg_match;
use function preg_match_all;
use function scandir;
use function strtr;
use function unlink;

/**
 * Tests the log rotation of the file logger.
 *
 * @group filelog
 */
class FileLogRotationTest extends FileLogTestBase {

  /**
   * A mock of the logfile service that provides the filename.
   *
   * @var \Drupal\filelog\LogFileManagerInterface
   */
  protected LogFileManagerInterface $fileManager;

  /**
   * A mock of the token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected Token $token;

  /**
   * A mock of the datetime.time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Force UTC time to avoid platform-specific effects.
    date_default_timezone_set('UTC');

    $this->fileManager = $this
      ->getMockBuilder(LogFileManager::class)
      ->disableOriginalConstructor()
      ->getMock();
    $this->fileManager
      ->method('getFileName')
      ->willReturn('vfs://filelog/' . LogFileManager::FILENAME);

    $this->token = $this
      ->getMockBuilder(Token::class)
      ->disableOriginalConstructor()
      ->getMock();
    $this->token
      ->method('replace')
      ->willReturnCallback([static::class, 'tokenReplace']);
    \Drupal::getContainer()->set('token', $this->token);

    $this->time = $this
      ->getMockBuilder(TimeInterface::class)
      ->getMock();
    $this->time
      ->method('getRequestTime')
      ->willReturn(86401);
  }

  /**
   * Test the log rotator with a variety of configurations and states.
   *
   * @param int $timestamp
   *   The time of the previous log rotation.
   * @param array $config
   *   The filelog settings.
   * @param array $files
   *   The files that are expected to exist after the test.
   *
   * @dataProvider provideRotationConfig
   */
  public function testRotation(int $timestamp, array $config, array $files): void {
    $root = 'vfs://filelog';

    $logFile = $root . '/' . LogFileManager::FILENAME;
    $data = "This is the log file content.\n";

    $configs = [
      'filelog.settings' => [
        'rotation' => $config,
        'location' => $root,
      ],
    ];
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $configFactory */
    $configFactory = $this->getConfigFactoryStub($configs);

    $state = $this->createMock(StateInterface::class);
    $state->method('get')
      ->with('filelog.rotation')
      ->willReturn($timestamp);

    file_put_contents($logFile, $data);
    $rotator = new LogRotator($configFactory,
                              $state,
                              $this->time,
                              $this->fileManager,
                              $this->fileSystem);
    try {
      $rotator->run();
    }
    catch (FileLogException $exception) {
      static::fail("Log rotation caused an exception: $exception");
    }

    // Check that all the expected files have the correct content.
    foreach ($files as $name) {
      $path = "$root/$name";
      $compressed = preg_match('/\.gz$/', $name) === 1;
      if ($compressed) {
        // Add the source file's timestamp to the expected gzip header.
        $timestamp = pack('L', filemtime($logFile));
        $expected = substr_replace(gzencode($data), $timestamp, 4, 4);
        // Ensure a fixed OS identifier.
        $expected[9] = "\x03";
      }
      else {
        $expected = $data;
      }

      static::assertEquals($expected, file_get_contents($path));

      // Delete the file after checking.
      unlink($path);
    }

    // Check that no other files exist.
    foreach (scandir('vfs://filelog') as $name) {
      if ($name === '.htaccess') {
        continue;
      }

      $path = "$root/$name";
      // The log file itself may persist, but must be empty.
      if ($name === LogFileManager::FILENAME) {
        static::assertStringEqualsFile($path, '');
      }
      // There may be subdirectories.
      else {
        static::assertDirectoryExists($path);
      }
    }
  }

  /**
   * Provide configuration and state for the rotation test.
   *
   * @return array
   *   All datasets for ::testRotation()
   */
  public function provideRotationConfig(): array {
    $config = [
      'schedule'    => 'daily',
      'delete'      => FALSE,
      'destination' => 'archive/[date:custom:Y/m/d].log',
      'gzip'        => FALSE,
    ];
    $data[] = [
      'timestamp' => 86400,
      'config'    => $config,
      'files'     => [LogFileManager::FILENAME],
    ];
    $data[] = [
      'timestamp' => 86399,
      'config'    => $config,
      'files'     => ['archive/1970/01/01.log'],
    ];

    $config['schedule'] = 'weekly';
    // 70/1/1 was a Thursday. Go back three days to the beginning of the week.
    $data[] = [
      'timestamp' => -259200,
      'config'    => $config,
      'files'     => [LogFileManager::FILENAME],
    ];
    $data[] = [
      'timestamp' => -259201,
      'config'    => $config,
      'files'     => ['archive/1969/12/28.log'],
    ];

    $config['schedule'] = 'monthly';
    $data[] = [
      'timestamp' => 0,
      'config'    => $config,
      'files'     => [LogFileManager::FILENAME],
    ];
    $data[] = [
      'timestamp' => -1,
      'config'    => $config,
      'files'     => ['archive/1969/12/31.log'],
    ];

    $config['gzip'] = TRUE;
    $data[] = [
      'timestamp' => -1,
      'config'    => $config,
      'files'     => ['archive/1969/12/31.log.gz'],
    ];

    $config['delete'] = TRUE;
    $data[] = [
      'timestamp' => -1,
      'config'    => $config,
      'files'     => [],
    ];

    $config['schedule'] = 'never';
    $data[] = [
      // About three years.
      'timestamp' => -100000000,
      'config'    => $config,
      'files'     => [LogFileManager::FILENAME],
    ];

    return $data;
  }

  /**
   * Mock Token::replace() only for [date:custom:...].
   *
   * @param string $text
   *   The text to be replaced.
   * @param array $data
   *   The placeholders.
   *
   * @return string
   *   The formatted text.
   */
  public static function tokenReplace(string $text, array $data): string {
    preg_match_all('/\[date:custom:(.*?)]/', $text, $matches, PREG_SET_ORDER);
    $replace = [];
    foreach ((array) $matches as $match) {
      $replace[$match[0]] = date($match[1], $data['date']);
    }
    return strtr($text, $replace);
  }

}

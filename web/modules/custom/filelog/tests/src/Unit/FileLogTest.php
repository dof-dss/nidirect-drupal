<?php

namespace Drupal\Tests\filelog\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\FileSecurity\FileSecurity;
use Drupal\Core\Logger\LogMessageParser;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Utility\Token;
use Drupal\filelog\LogFileManager;
use Drupal\filelog\Logger\FileLog;
use Drupal\filelog\LogMessage;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use function file_get_contents;
use function strtr;

/**
 * Test the file logger.
 *
 * @group filelog
 */
class FileLogTest extends FileLogTestBase {

  /**
   * A mock of the token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected Token $token;

  /**
   * The logger.log_message_parser service.
   *
   * @var \Drupal\Core\Logger\LogMessageParserInterface
   */
  protected LogMessageParserInterface $logMessageParser;

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

    $this->token = $this->getMockBuilder(Token::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->token->method('replace')
      ->willReturnCallback([static::class, 'tokenReplace']);

    \Drupal::getContainer()->set('token', $this->token);

    $this->time = $this->createMock(TimeInterface::class);
    $this->time->method('getRequestTime')
      ->willReturn($_SERVER['REQUEST_TIME']);

    $this->logMessageParser = new LogMessageParser();

  }

  /**
   * Test a particular configuration.
   *
   * Ensure that it logs the correct events.
   *
   * @param array $config
   *   The filelog settings.
   * @param array $events
   *   The events to be logged.
   * @param string $expected
   *   The expected content of the log file after the test.
   *
   * @dataProvider providerFileLog
   */
  public function testFileLog(array $config, array $events, string $expected = ''): void {
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $configFactory */
    $configFactory = $this->getConfigFactoryStub(
      ['filelog.settings' => $config]
    );

    /** @var \Drupal\Core\State\StateInterface|\PHPUnit\Framework\MockObject\MockObject $state */
    $state_data = ['filelog.rotation' => 0];
    $state = $this->createMock(StateInterface::class);
    $state->method('get')
      ->willReturnCallback(
        static function ($key) use (&$state_data) {
          return $state_data[$key];
        }
      );
    $state->method('set')
      ->willReturnCallback(
        static function ($key, $value) use (&$state_data) {
          $state_data[$key] = $value;
        }
      );

    $logger = new FileLog(
      $configFactory,
      $state,
      $this->time,
      $this->logMessageParser,
      $logFileManager = new LogFileManager($configFactory, $this->fileSystem),
      $eventDispatcher = $this->createMock(EventDispatcherInterface::class),
    );

    foreach ($events as $event) {
      $logger->log($event['level'], $event['message'], $event['context']);
    }

    // The logger should ensure .htaccess whenever it opens the log file.
    if ($expected) {
      $logPath = $configFactory->get('filelog.settings')->get('location');
      static::assertStringEqualsFile("$logPath/.htaccess", FileSecurity::htaccessLines(), '.htaccess file written correctly.');
      static::assertEquals(0664, 0777 & fileperms($logFileManager->getFileName()), 'Logfile has 0664 permissions.');
    }

    // Read log output and remove file for the next test.
    $content = '';
    if ($this->virtualFileSystem->hasChild(LogFileManager::FILENAME)) {
      $content = file_get_contents(
        $this->virtualFileSystem->getChild(LogFileManager::FILENAME)->url()
      );
      $this->virtualFileSystem->removeChild(LogFileManager::FILENAME);
    }

    static::assertEquals($expected, $content);

    // Check that the timestamp was saved if and only if a log was created.
    $timestamp = $state->get('filelog.rotation');
    static::assertEquals(
      $content ? $_SERVER['REQUEST_TIME'] : 0,
      $timestamp
    );
  }

  /**
   * Provide data for the level-checking test.
   *
   * @return array
   *   All datasets for ::testFileLog().
   */
  public function providerFileLog(): array {
    $config = [
      'enabled'       => TRUE,
      'location'      => 'vfs://filelog',
      'level'         => 7,
      'channels_type' => 'exclude',
      'channels'      => [],
      'format'        => '[log:level] [log:message]',
    ];

    $levels = LogMessage::getLevels();
    $events = [];
    $messages = [];
    for ($i = 0; $i <= 7; $i++) {
      $events[] = [
        'level'   => $i,
        'message' => "This is message @i.\n LD5}5>~\\8AiU * VH",
        'context' => [
          '@i'        => $i,
          'timestamp' => 0,
          'channel'   => "channel_$i",
        ],
      ];
      $messages[] = $levels[$i] . " This is message $i.\\n LD5}5>~\\8AiU * VH";
    }

    $data = [];
    for ($i = 0, $iMax = count($levels); $i <= $iMax; $i++) {
      $expected = implode("\n", array_slice($messages, 0, $i + 1)) . "\n";
      $data[$i] = [
        'config'   => ['level' => $i] + $config,
        'events'   => $events,
        'expected' => $expected,
      ];
    }

    $data[] = [
      'config'   => ['enabled' => FALSE] + $config,
      'events'   => $events,
      'expected' => '',
    ];

    $data[] = [
      'config'   => ['channels_type' => 'include'] + $config,
      'events'   => $events,
      'expected' => '',
    ];

    $data[] = [
      'config'   => [
        'channels_type' => 'include',
        'channels'      => ['channel_3'],
      ] + $config,
      'events'   => $events,
      'expected' => $messages[3] . "\n",
    ];

    $data[] = [
      'config'   => ['channels' => ['channel_3']] + $config,
      'events'   => $events,
      'expected' => implode("\n",
        array_slice($messages, 0, 3) + array_slice($messages, 4, 4, TRUE)
      ) . "\n",
    ];

    return $data;
  }

  /**
   * A very primitive mock for the token service.
   *
   * The full token integration is tested separately.
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
    /** @var \Drupal\filelog\LogMessage $message */
    $message = $data['log'];
    return strtr(
      $text,
      [
        '[log:message]' => $message->getText(),
        '[log:level]'   => $message->getLevel(),
      ]
    );
  }

}

<?php

namespace Drupal\filelog\Logger;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Utility\Token;
use Drupal\filelog\Event\FileLogEvents;
use Drupal\filelog\Event\FileShouldLogEvent;
use Drupal\filelog\FileLogException;
use Drupal\filelog\LogFileManagerInterface;
use Drupal\filelog\LogMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use function file_exists;
use function fopen;
use function fwrite;
use function watchdog_exception;

/**
 * File-based logger.
 */
class FileLog implements LoggerInterface {

  use RfcLoggerTrait;
  use DependencySerializationTrait;

  /**
   * The filelog settings.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected Config $config;

  /**
   * The state system, for updating the filelog.rotation timestamp.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * The (lazy loaded) dependency injection (DI) container.
   *
   * @var ?\Drupal\Component\DependencyInjection\ContainerInterface
   */
  protected ?ContainerInterface $container;

  /**
   * The (lazy loaded) token object.
   *
   * @var ?\Drupal\Core\Utility\Token
   */
  protected ?Token $token;

  /**
   * The log message parser, for formatting the log messages.
   *
   * @var \Drupal\Core\Logger\LogMessageParserInterface
   */
  protected LogMessageParserInterface $parser;

  /**
   * The time system.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * The currently opened log file.
   *
   * @var resource
   */
  protected $logFile;

  /**
   * The STDERR fallback.
   *
   * @var resource
   */
  protected $stderr;

  /**
   * The log-file manager, providing file-handling methods.
   *
   * @var \Drupal\filelog\LogFileManagerInterface
   */
  protected LogFileManagerInterface $fileManager;

  /**
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * FileLog constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config.factory service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The datetime.time service.
   * @param \Drupal\Core\Logger\LogMessageParserInterface $parser
   *   The logger.log_message_parser service.
   * @param \Drupal\filelog\LogFileManagerInterface $fileManager
   *   The filelog.file_manager service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   Allows providing hooks on domain-specific lifecycles by dispatching events.
   */
  public function __construct(ConfigFactoryInterface $configFactory, StateInterface $state, TimeInterface $time, LogMessageParserInterface $parser, LogFileManagerInterface $fileManager, EventDispatcherInterface $event_dispatcher) {
    $this->config = $configFactory->get('filelog.settings');
    $this->state = $state;
    $this->time = $time;
    $this->parser = $parser;
    $this->fileManager = $fileManager;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * Open the logfile for writing.
   *
   * @return bool
   *   Returns TRUE if the log file is available for writing.
   *
   * @throws \Drupal\filelog\FileLogException
   */
  protected function openFile(): bool {
    if ($this->logFile) {
      return TRUE;
    }

    // When creating a new log file, save the creation timestamp.
    $filename = $this->fileManager->getFileName();
    $create = !file_exists($filename);
    if (!$this->fileManager->ensurePath()) {
      $this->logFile = $this->stderr();
      throw new FileLogException('The log directory has disappeared.');
    }
    if ($this->logFile = fopen($filename, 'ab')) {
      if ($create) {
        $this->fileManager->setFilePermissions();
        $this->state->set('filelog.rotation', $this->time->getRequestTime());
      }
      return TRUE;
    }

    // Log errors to STDERR until the end of the current request.
    $this->logFile = $this->stderr();
    throw new FileLogException('The logfile could not be opened for writing. Logging to STDERR.');
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []): void {
    if (!$this->shouldLog($level, $message, $context)) {
      return;
    }

    try {
      $entry = $this->render($level, $message, $context);
    }
    catch (\Exception $e) {
      return;
    }

    try {
      $this->openFile();
      $this->write($entry);
    }
    catch (FileLogException $error) {
      // Log the exception, unless we were already logging a filelog error.
      if ($context['channel'] !== 'filelog') {
        watchdog_exception('filelog', $error);
      }
      // Write the message directly to STDERR.
      fwrite($this->stderr(), $entry . "\n");
    }
  }

  /**
   * Decides whether a message should be logged or ignored.
   *
   * @param mixed $level
   *   Severity level of the log message.
   * @param string $message
   *   Content of the log message.
   * @param array $context
   *   Context of the log message.
   *
   * @return bool
   *   TRUE if the message should be logged, FALSE otherwise.
   */
  protected function shouldLog(mixed $level, string $message, array $context = []): bool {
    // Ignore any messages below the configured severity.
    // (Severity decreases with level.)
    if ($this->config->get('enabled') && $level <= $this->config->get('level')) {
      // Dispatch a log event in case other modules have an opinion on this
      // message.
      $event = new FileShouldLogEvent($level, $message, $context);
      $event->setDecision(FALSE);
      if (($this->config->get('channels_type') === 'include')
        === in_array($context['channel'], $this->config->get('channels'), TRUE)) {
        $event->setDecision(TRUE);
      }
      $this->eventDispatcher->dispatch($event, FileLogEvents::FILE_LOG_SHOULD_LOG);
      return $event->getDecision();
    }
    return FALSE;
  }

  /**
   * Renders a message to a string.
   *
   * @param mixed $level
   *   Severity level of the log message.
   * @param string $message
   *   Content of the log message.
   * @param array $context
   *   Context of the log message.
   *
   * @return string
   *   The formatted message.
   */
  protected function render(mixed $level, string $message, array $context = []): string {
    // Populate the message placeholders.
    $variables = $this->parser->parseMessagePlaceholders($message, $context);
    // Pass in bubbleable metadata that are just discarded later to prevent a
    // LogicException due to too early rendering. The metadata of the string
    // is not needed as it is not used for cacheable output but for writing to a
    // logfile.
    $bubbleable_metadata_to_discard = new BubbleableMetadata();
    $log = new LogMessage($level, $message, $variables, $context);
    $entry = $this->token()->replace(
      $this->config->get('format'),
      ['log' => $log],
      [],
      $bubbleable_metadata_to_discard
    );
    return PlainTextOutput::renderFromHtml($entry);
  }

  /**
   * Open STDERR resource, or use STDERR constant if available.
   *
   * The STDERR constant is not defined in all PHP environments.
   *
   * @return resource
   *   Reference to the STDERR stream resource.
   */
  protected function stderr() {
    if ($this->stderr === NULL) {
      $this->stderr = defined('STDERR') ? STDERR : fopen('php://stderr', 'wb');
    }
    return $this->stderr;
  }

  /**
   * Write an entry to the logfile.
   *
   * @param string $entry
   *   The value to write. This should contain no newline characters.
   *
   * @throws \Drupal\filelog\FileLogException
   */
  protected function write(string $entry): void {
    if (!fwrite($this->logFile, $entry . "\n")) {
      throw new FileLogException('The message could not be written to the logfile.');
    }
  }

  /**
   * Get Dependency Injection container.
   *
   * @return \Drupal\Component\DependencyInjection\ContainerInterface
   *   Current Dependency Injection container.
   */
  protected function getContainer(): ContainerInterface {
    if (!isset($this->container)) {
      $this->container = \Drupal::getContainer();
    }
    return $this->container;
  }

  /**
   * Get lazy-loaded Token service.
   *
   * @return \Drupal\Core\Utility\Token
   *   Token service.
   */
  protected function token(): Token {
    if (!isset($this->token)) {
      $this->token = $this->getContainer()->get('token');
    }
    return $this->token;
  }

}

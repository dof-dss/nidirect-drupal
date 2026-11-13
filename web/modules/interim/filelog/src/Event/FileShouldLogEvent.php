<?php

namespace Drupal\filelog\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Responds to xi_log.should_log_event event.
 *
 * For message to be logged `getDecision()` method must return TRUE. By default
 * decision is set to TRUE. To set value use `setDecision()` method. It is
 * recommended in case of negative decision to set decision to FALSE and
 * immediately stop event propagation, because it might be the case that other
 * event subscriber returns positive result. To stop propagation use event
 * method `stopPropagation()`.
 */
class FileShouldLogEvent extends Event {

  /**
   * Log message level.
   *
   * @var int
   */
  protected $level;

  /**
   * Log message.
   *
   * @var string
   */
  protected $message;

  /**
   * Log message context.
   *
   * @var array
   */
  protected $context;

  /**
   * Decision whether to log message or not.
   *
   * @var bool
   */
  protected $decision = TRUE;

  /**
   * ShouldLogEvent constructor.
   *
   * @param int $level
   *   Log level value.
   * @param string $message
   *   Log message.
   * @param array $context
   *   Context object.
   */
  public function __construct($level, $message, array $context = []) {
    $this->setLevel($level);
    $this->setMessage($message);
    $this->setContext($context);
  }

  /**
   * @return bool
   *   Decision value.
   */
  public function getDecision() {
    return $this->decision;
  }

  /**
   * @param mixed $decision
   *   Decision value?
   */
  public function setDecision($decision) {
    $this->decision = $decision;
  }

  /**
   * Gets log message context.
   *
   * Context array has following structure:
   * [
   *   'backtrace',
   *   'exception',
   *   'uid',
   *   'channel',
   *   'link',
   *   'request_uri',
   *   'referer',
   *   'ip',
   *   'timestamp',
   * ]
   *
   * Event subscribers can make a decision based on any parameter context array
   * has, as well as level and message itself. It is also possible to get
   * message placeholders with `logger.log_message_parser` service.
   *
   * @see \Drupal\dblog\Logger\DbLog::log
   *
   * @return array
   *   Message log context.
   */
  public function getContext() {
    return $this->context;
  }

  /**
   * @param mixed $context
   *   Potentially anything returned.
   */
  public function setContext($context) {
    $this->context = $context;
  }

  /**
   * @return string
   *   The message content.
   */
  public function getMessage() {
    return $this->message;
  }

  /**
   * @param mixed $message
   *   Potentially anything returned.
   */
  public function setMessage($message) {
    $this->message = $message;
  }

  /**
   * @return int
   *   The log level value.
   */
  public function getLevel() {
    return $this->level;
  }

  /**
   * @param int $level
   *   The level to set.
   * @return void
   *   Nada
   */
  public function setLevel($level): void {
    $this->level = $level;
  }

}

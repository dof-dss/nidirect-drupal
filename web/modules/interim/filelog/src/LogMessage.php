<?php

namespace Drupal\filelog;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use function in_array;
use function str_replace;
use function strip_tags;
use function strtr;

/**
 * Represents a single log event.
 */
class LogMessage {

  /**
   * Untranslated level strings.
   *
   * @var string[]
   */
  protected static array $levels;

  /**
   * The log message, with placeholders.
   *
   * @var string
   */
  protected string $message;

  /**
   * The processed log message, with placeholders replaced.
   *
   * @var string
   */
  protected string $text;

  /**
   * Placeholders of the log message.
   *
   * @var array
   */
  protected array $placeholders;

  /**
   * Variables of the log message.
   *
   * Identical to placeholders, but with format prefixes stripped.
   *
   * @var array
   */
  protected array $variables;

  /**
   * Context variables of the log message.
   *
   * @var array
   */
  protected array $context;

  /**
   * Severity level.
   *
   * @var int
   */
  protected int $level;

  /**
   * User who triggered the event.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $user;

  /**
   * LogMessage constructor.
   *
   * @param int $level
   *   Severity level.
   * @param string $message
   *   Message content.
   * @param array $variables
   *   Placeholder variables.
   * @param array $context
   *   Context variables.
   */
  public function __construct(int $level, string $message, array $variables, array $context) {
    $this->level = $level;
    // Store the original placeholders for rendering the message.
    $this->placeholders = $variables;
    $this->message = $message;

    // Strip the variable format prefixes.
    foreach ($variables as $key => $value) {
      if (in_array($key[0], ['%', '!', '@', ':'], TRUE)) {
        $variables[substr($key, 1)] = $value;
        unset($variables[$key]);
      }
    }

    $this->variables = $variables;
    $this->context = $context + [
      'uid'         => NULL,
      'channel'     => NULL,
      'ip'          => NULL,
      'request_uri' => NULL,
      'referer'     => NULL,
      'timestamp'   => NULL,
    ];
  }

  /**
   * Get untranslated level strings.
   *
   * @return string[]
   *   An associative array of RFC levels to labels.
   */
  public static function getLevels(): array {
    if (!isset(static::$levels)) {
      static::$levels = RfcLogLevel::getLevels();
      foreach (static::$levels as $id => $label) {
        /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $label */
        static::$levels[$id] = $label->getUntranslatedString();
      }
    }

    return static::$levels;
  }

  /**
   * Get the log channel.
   *
   * @return string
   *   The log channel.
   */
  public function getChannel(): string {
    return $this->context['channel'];
  }

  /**
   * Get the severity level.
   *
   * @return string
   *   The severity level.
   */
  public function getLevel(): string {
    return static::getLevels()[$this->level];
  }

  /**
   * Get the rendered text of the message.
   *
   * @return string
   *   The rendered text.
   */
  public function getText(): string {
    if (!isset($this->text)) {
      $this->text = $this->message;
      if (!empty($this->placeholders)) {
        $this->text = strtr($this->text, $this->placeholders);
      }
      $this->text = str_replace("\n", '\n', strip_tags($this->text));
    }
    return $this->text;
  }

  /**
   * Get the request URI of the message.
   *
   * @return string
   *   The request URI.
   */
  public function getLocation(): string {
    return $this->context['request_uri'];
  }

  /**
   * Get the IP that triggered the message.
   *
   * @return string
   *   The IP.
   */
  public function getIp(): string {
    return $this->context['ip'] ?: '0.0.0.0';
  }

  /**
   * Get the referrer of the message.
   *
   * @return string|null
   *   The referrer, or NULL.
   */
  public function getReferrer(): ?string {
    return $this->context['referer'] ?? NULL;
  }

  /**
   * Get an arbitrary variable.
   *
   * @param string $name
   *   The variable name (without format prefix).
   *
   * @return string|null
   *   The value.
   */
  public function getVariable(string $name): ?string {
    return $this->variables[$name] ?? NULL;
  }

  /**
   * Get an arbitrary context variable.
   *
   * @param string $name
   *   The variable name.
   *
   * @return string|null
   *   The value.
   */
  public function getContext(string $name): ?string {
    return $this->context[$name] ?? NULL;
  }

  /**
   * Get the user who triggered the message.
   *
   * @return \Drupal\user\UserInterface
   *   The user object.
   */
  public function getUser(): UserInterface {
    if (!isset($this->user)) {
      if (isset($this->context['uid']) && $user = User::load($this->context['uid'])) {
        /** @var \Drupal\user\UserInterface user */
        $this->user = $user;
      }
      else {
        $this->user = User::load(\Drupal::currentUser()->id());
      }
    }
    return $this->user;
  }

  /**
   * Get the timestamp of the message.
   *
   * @return int
   *   The timestamp.
   */
  public function getTimestamp(): int {
    return $this->context['timestamp'];
  }

}

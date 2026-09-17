<?php

namespace Drupal\nidirect_school_closures;

/**
 * Class for managing individual school closures.
 */
class SchoolClosure {

  /**
   * The name of the school.
   *
   * @var string
   */
  protected $name;

  /**
   * Alternative school name (without diacritics).
   *
   * @var string
   */
  protected $altName;

  /**
   * The location of the school.
   *
   * @var string
   */
  protected $location;

  /**
   * DateTime the closure is in effect.
   *
   * @var \DateTime
   */
  protected $date;

  /**
   * DateTime the closure ends, if it spans more than one day.
   *
   * @var \DateTime|null
   */
  protected $dateTo;

  /**
   * Text reason for the closure.
   *
   * @var string
   */
  protected $reason;

  /**
   * Constructor for SchoolClosure class.
   *
   * @param string $name
   *   Name of the closure.
   * @param string $location
   *   Location of the closure.
   * @param \DateTime $date
   *   Date of closure.
   * @param string $reason
   *   Reason for closure.
   * @param \DateTime|null $dateTo
   *   End date of closure, if it spans more than one day.
   */
  public function __construct(string $name, string $location, \DateTime $date, string $reason, ?\DateTime $dateTo = NULL) {
    $this->name = $name;
    $this->location = $location;
    $this->date = $date;
    $this->reason = $reason;
    $this->dateTo = $dateTo;

    // Call processors.
    $this->processAltName();
  }

  /**
   * Get the school closure data.
   *
   * @return array
   *   Associative array of closure data.
   */
  public function getData() {
    return [
      'name' => $this->name,
      'altname' => $this->altName,
      'location' => $this->location,
      'date' => $this->date,
      'dateTo' => $this->dateTo,
      'reason' => $this->reason,
    ];
  }

  /**
   * Return if the closure date has expired.
   */
  public function isExpired() {
    $today = new \DateTime('now', new \DateTimeZone('Europe/London'));
    // Reset the clock to avoid issues with time comparisons.
    $today->setTime(0, 0, 0);

    $compareDate = $this->dateTo ?? $this->date;

    return ($compareDate < $today) ? TRUE : FALSE;
  }

  /**
   * Process alternative school names.
   */
  protected function processAltname() {
    // Add alternative names for Irish name schools.
    $pattern = '/[ÁÉÍÓÚáéíóú]/';
    if (preg_match($pattern, $this->name)) {
      $transliteration = \Drupal::service('transliteration');
      $this->altName = $transliteration->removeDiacritics($this->name);

    }
  }

}

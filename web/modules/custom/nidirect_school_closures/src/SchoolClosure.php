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
   * Text reason for the closure.
   *
   * @var string
   */
  protected $reason;

  /**
   * Array of friendly reason texts.
   */
  protected const REASONS = [
    "adverse weather conditions/exceptionally heavy snowfall"                     => "due to adverse weather.",
    "no water supply"                                                             => "due to no water supply.",
    "lack of heating"                                                             => "due to no heating.",
    "road closures"                                                               => "due to road closures.",
    "electricity failure"                                                         => "due to no electricity.",
    "other - please contact the school for further information"                   => ". Contact the school for more information.",
    "used as a polling station for parliamentary/local government elections"      => "due to use as a polling station for an election.",
    "death of a member of staff, pupil or another person working at the school"   => "due to a death in the school family.",
    "flooding or burst pipes"                                                     => "due to flooding or a burst pipe.",
  ];

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
   */
  public function __construct(string $name, string $location, \DateTime $date, string $reason) {
    $this->name = $name;
    $this->location = $location;
    $this->date = $date;
    $this->reason = $reason;

    // Call processors.
    $this->processAltName();
    $this->processLocation();
    $this->processReason();
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

    return ($this->date < $today) ? TRUE : FALSE;
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

  /**
   * Process the school location.
   */
  protected function processLocation() {
    /* If the first word of the title is also the first word of the
     * location, remove it from the location. So if we have
     *
     * Portadown Primary School, Portadown, County Armagh
     *
     * it should be changed to
     *
     * Portadown Primary School, County Armagh
     */
    if (!empty($this->name) && !empty($this->location)) {
      $title_arr = explode(' ', $this->name);
      $first_word = $title_arr[0] . ', ';
      if (substr($this->location, 0, strlen($first_word)) == $first_word) {
        $this->location = substr($this->location, strlen($first_word));
      }
    }
  }

  /**
   * Process the reason for the closure.
   */
  protected function processReason() {
    if (isset(static::REASONS[strtolower($this->reason)])) {
      // Do the comparison in lowercase because $reason_map array keys are
      // lowercase (to allow for variations in capitalisation)
      $this->reason = static::REASONS[strtolower($this->reason)];
    }
    else {
      // Try to make the existing string consistent with the Nidirect versions.
      $this->reason = '. ' . ucfirst($this->reason);
      if (!preg_match('/.*\.$/', $this->reason)) {
        $this->reason = $this->reason . '.';
      }
    }
  }

}

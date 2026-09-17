<?php

namespace Drupal\nidirect_school_closures;

/**
 * Maps school closure reason types to display fragments.
 *
 * Kept as its own class so that swapping the static map below for an
 * API-driven reason list in future only requires changing this class.
 */
class SchoolClosureReasonMapper {

  /**
   * Reason fragments keyed by the API's reasonTypeId.
   *
   * Fragments are lowercase, with no "due to" prefix and no trailing
   * punctuation, so they can be combined by combine().
   */
  protected const REASONS = [
    1 => 'adverse weather',
    2 => 'use as a polling station for an election',
    4 => 'death of a member of staff, pupil or another person working at the school',
  ];

  /**
   * Returns the display fragment for a reason type.
   *
   * @param int $reasonTypeId
   *   The API's reasonTypeId.
   * @param string $fallbackText
   *   The API's own reasonType text, used if the ID isn't mapped.
   *
   * @return string
   *   The display fragment.
   */
  public function getFragment(int $reasonTypeId, string $fallbackText): string {
    return static::REASONS[$reasonTypeId] ?? strtolower($fallbackText);
  }

  /**
   * Combines a closure's reasons into a single display sentence.
   *
   * @param array $reasons
   *   Array of reasons, each with 'reasonTypeId' and 'reasonType' keys.
   *
   * @return string
   *   The combined sentence, e.g. "due to adverse weather and use as a
   *   polling station for an election."
   */
  public function combine(array $reasons): string {
    $fragments = [];
    foreach ($reasons as $reason) {
      $fragments[] = $this->getFragment((int) $reason['reasonTypeId'], $reason['reasonType'] ?? '');
    }

    if (empty($fragments)) {
      return '';
    }

    if (count($fragments) === 1) {
      $joined = $fragments[0];
    }
    else {
      $last = array_pop($fragments);
      $joined = implode(', ', $fragments) . ' and ' . $last;
    }

    return 'due to ' . $joined . '.';
  }

}

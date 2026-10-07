<?php

namespace Drupal\nidirect_school_closures;

/**
 * Interface for implementing a School Closures Service.
 */
interface SchoolClosuresServiceInterface {

  /**
   * Returns school closures data.
   *
   * Returned array elements represent one school each, and should comprise:
   * [
   *  'name' => '',
   *  'altname' => '',
   *  'location' => '',
   *  'closures' => [
   *    ['date' => '', 'dateTo' => '', 'reason' => ''],
   *    ...
   *  ],
   * ]
   *
   * A school may have more than one current closure, so all of them are
   * listed under 'closures' rather than the school appearing more than once.
   *
   * @return array
   *   An array of associative arrays for schools, sorted by their earliest
   *   current closure date ascending.
   */
  public function getClosures();

  /**
   * Returns last updated date.
   *
   * @return \DateTime
   *   A DateTime of when the closures data was last updated.
   */
  public function getUpdated(): \DateTime|NULL;

  /**
   * Returns if the closure service encountered errors.
   *
   * Return TRUE if requests to the data service have failed and no data
   * is available. We use this to display a 'Contact your school' message.
   *
   * @return bool
   *   A boolean state if errors were encountered when requesting data.
   */
  public function hasErrors(): bool;

}

<?php

namespace Drupal\nidirect_school_closures;

/**
 * Interface for implementing a School Closures Service.
 */
interface SchoolClosuresServiceInterface {

  /**
   * Returns school closures data.
   *
   * Returned array elements should comprise:
   * [
   *  'name' => '',
   *  'altname' => '',
   *  'location' => '',
   *  'date' => '',
   *  'reason' => '',
   * ]
   *
   * @return array
   *   An array of associative arrays for school closures sorted by date asc.
   */
  public function getClosures();

  /**
   * Returns last updated date.
   *
   * @return \DateTime
   *   A DateTime of when the closures data was last updated.
   */
  public function getUpdated();

  /**
   * Returns if the closure service encountered errors.
   *
   * Return TRUE if requests to the data service have failed and no data
   * is available. We use this to display a 'Contact your school' message.
   *
   * @return bool
   *   A boolean state if errors were encountered when requesting data.
   */
  public function hasErrors();

}

<?php

namespace Drupal\nidirect_gp\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining GP entities.
 *
 * @ingroup nidirect_gp
 */
interface GpInterface extends ContentEntityInterface, RevisionLogInterface, EntityChangedInterface, EntityOwnerInterface {

  // Add get/set methods for your configuration properties here.

  /**
   * Get the display name of the GP.
   *
   * @return string
   *   Title, first and last name of the GP as single string.
   */
  public function getDisplayName();

  /**
   * Gets the GP first name.
   *
   * @return string
   *   First name of the GP.
   */
  public function getFirstName();

  /**
   * Sets the GP first name.
   *
   * @param string $first_name
   *   The GP first name.
   *
   * @return \Drupal\nidirect_gp\Entity\GpInterface
   *   The called GP entity.
   */
  public function setFirstName($first_name);

  /**
   * Gets the GP last name.
   *
   * @return string
   *   Last name of the GP.
   */
  public function getLastName();

  /**
   * Sets the GP last name.
   *
   * @param string $last_name
   *   The GP last name.
   *
   * @return \Drupal\nidirect_gp\Entity\GpInterface
   *   The called GP entity.
   */
  public function setLastName($last_name);

  /**
   * Gets the GP cypher.
   *
   * @return string
   *   Cypher of the GP.
   */
  public function getCypher();

  /**
   * Sets the GP cypher.
   *
   * @param string $cypher
   *   The GP cypher.
   *
   * @return \Drupal\nidirect_gp\Entity\GpInterface
   *   The called GP entity.
   */
  public function setCypher($cypher);

  /**
   * Gets the GP creation timestamp.
   *
   * @return int
   *   Creation timestamp of the GP.
   */
  public function getCreatedTime();

  /**
   * Sets the GP creation timestamp.
   *
   * @param int $timestamp
   *   The GP creation timestamp.
   *
   * @return \Drupal\nidirect_gp\Entity\GpInterface
   *   The called GP entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the GP published status indicator.
   *
   * Unpublished GP are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the GP is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a GP.
   *
   * @param bool $published
   *   TRUE to set this GP to published, FALSE to set it to unpublished.
   *
   * @return \Drupal\nidirect_gp\Entity\GpInterface
   *   The called GP entity.
   */
  public function setPublished($published);

  /**
   * Gets the GP revision creation timestamp.
   *
   * @return int
   *   The UNIX timestamp of when this revision was created.
   */
  public function getRevisionCreationTime();

  /**
   * Sets the GP revision creation timestamp.
   *
   * @param int $timestamp
   *   The UNIX timestamp of when this revision was created.
   *
   * @return \Drupal\nidirect_gp\Entity\GpInterface
   *   The called GP entity.
   */
  public function setRevisionCreationTime($timestamp);

  /**
   * Gets the GP revision author.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity for the revision author.
   */
  public function getRevisionUser();

  /**
   * Sets the GP revision author.
   *
   * @param int $uid
   *   The user ID of the revision author.
   *
   * @return \Drupal\nidirect_gp\Entity\GpInterface
   *   The called GP entity.
   */
  public function setRevisionUserId($uid);

}

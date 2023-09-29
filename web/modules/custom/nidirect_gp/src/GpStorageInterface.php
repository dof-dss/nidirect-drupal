<?php

namespace Drupal\nidirect_gp;

use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\nidirect_gp\Entity\GpInterface;

/**
 * Defines the storage handler class for GP entities.
 *
 * This extends the base storage class, adding required special handling for
 * GP entities.
 *
 * @ingroup nidirect_gp
 */
interface GpStorageInterface extends ContentEntityStorageInterface {

  /**
   * Gets a list of GP revision IDs for a specific GP.
   *
   * @param \Drupal\nidirect_gp\Entity\GpInterface $entity
   *   The GP entity.
   *
   * @return int[]
   *   GP revision IDs (in ascending order).
   */
  public function revisionIds(GpInterface $entity);

  /**
   * Gets a list of revision IDs having a given user as GP author.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user entity.
   *
   * @return int[]
   *   GP revision IDs (in ascending order).
   */
  public function userRevisionIds(AccountInterface $account);

  /**
   * Counts the number of revisions in the default language.
   *
   * @param \Drupal\nidirect_gp\Entity\GpInterface $entity
   *   The GP entity.
   *
   * @return int
   *   The number of revisions in the default language.
   */
  public function countDefaultLanguageRevisions(GpInterface $entity);

  /**
   * Unsets the language for all GP with the given language.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The language object.
   */
  public function clearRevisionsLanguage(LanguageInterface $language);

}

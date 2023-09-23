<?php

namespace Drupal\nidirect_taxonomy_navigator;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;

/**
 * Provides user access/permission checks for taxonomies.
 *
 * Returns an AccessResult object. To check user access against this
 * you should use the isAllowed() and isDisallowed() methods provided
 * by the class.
 */
class TaxonomyNavigatorAccess implements AccessInterface {

  /**
   * Determine if a user can administer vocabularies.
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden
   *   A Drupal AccessResult object.
   */
  public static function canAdministerVocabularies() {
    return self::taxonomyAccess('administer');
  }

  /**
   * Determine if a user can view taxonomy terms.
   *
   * @param string $vocabulary
   *   A vocabulary id.
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden
   *   A Drupal AccessResult object.
   */
  public static function canViewTerms($vocabulary) {
    return self::taxonomyAccess('view', $vocabulary);
  }

  /**
   * Determine if a user can edit taxonomy terms.
   *
   * @param string $vocabulary
   *   A vocabulary id.
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden
   *   A Drupal AccessResult object.
   */
  public static function canEditTerms($vocabulary) {
    return self::taxonomyAccess('edit', $vocabulary);
  }

  /**
   * Determine if a user can delete taxonomy terms.
   *
   * @param string $vocabulary
   *   A vocabulary id.
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden
   *   A Drupal AccessResult object.
   */
  public static function canDeleteTerms($vocabulary) {
    return self::taxonomyAccess('delete', $vocabulary);
  }

  /**
   * Determine if a user can reorder taxonomy terms.
   *
   * @param string $vocabulary
   *   A vocabulary id.
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden
   *   A Drupal AccessResult object.
   */
  public static function canReorderTerms($vocabulary) {
    return self::taxonomyAccess('reorder', $vocabulary);
  }

  /**
   * Helper function determining a users taxonomy permissions.
   *
   * @param string $type
   *   The type of permission to check for.
   * @param string|null $vocabulary
   *   A vocabulary id.
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden
   *   A Drupal AccessResult object.
   */
  public static function taxonomyAccess($type, $vocabulary = NULL) {
    $moduleHandler = \Drupal::service('module_handler');
    $taf_enabled = $moduleHandler->moduleExists('taxonomy_access_fix');

    // Generate a permission query using taxonomy_access_fix permissions or
    // fallback to permissions provided by the core taxonomy module.
    switch ($type) {
      case 'administer':
        $permission_query = 'administer taxonomy';
        break;

      case 'view':
        $permission_query = ($taf_enabled) ? 'view terms in ' . $vocabulary : 'access taxonomy overview';
        break;

      case 'edit':
        $permission_query = ($taf_enabled) ? 'edit terms in ' . $vocabulary : $vocabulary . ': Edit terms';
        break;

      case 'delete':
        $permission_query = ($taf_enabled) ? 'delete terms in ' . $vocabulary : $vocabulary . ': Delete terms';
        break;

      case 'reorder':
        $permission_query = ($taf_enabled) ? 'reorder terms in ' . $vocabulary : $vocabulary . ': Edit terms';
        break;

    }

    if (!empty($permission_query)) {
      $result = \Drupal::currentUser()->hasPermission($permission_query);
      return $result ? AccessResult::allowed() : AccessResult::forbidden();
    }

    return AccessResult::forbidden();
  }

}

<?php

namespace Drupal\nidirect_gp;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the GP entity.
 *
 * @see \Drupal\nidirect_gp\Entity\Gp.
 */
class GpAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\nidirect_gp\Entity\GpInterface $entity */
    switch ($operation) {
      case 'view':
        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished gp entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'view published gp entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit gp entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete gp entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add gp entities');
  }

}

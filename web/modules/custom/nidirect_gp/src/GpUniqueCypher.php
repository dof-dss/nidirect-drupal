<?php

namespace Drupal\nidirect_gp;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Check if a GP Cypher is unique.
 */
class GpUniqueCypher {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Determine if the provided GP cypher is unique or already exists.
   *
   * @param string $cypher
   *   The title of the content.
   * @param array $exclude
   *   List of gp entity ids to exclude from the check, if any.
   *
   * @return bool
   *   Return if the cypher is unique or not.
   */
  public function isCypherUnique(string $cypher, array $exclude = []) {
    $is_unique = TRUE;

    $result = $this->entityTypeManager->getStorage('gp')->loadByProperties([
      'cypher' => $cypher,
    ]);

    if (!empty($result)) {
      // Ignore any entity ids in the exclude list.
      foreach ($result as $gp) {
        if (!in_array($gp->id(), $exclude)) {
          $is_unique = FALSE;
        }
      }
    }

    return $is_unique;
  }

}

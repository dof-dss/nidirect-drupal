<?php

namespace Drupal\nidirect_gp;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityAutocompleteMatcher;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityTypeManager;

/**
 * Autocomplete matcher for GP's.
 */
class GpAutocompleteMatcher extends EntityAutocompleteMatcher {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Constructs a EntityAutocompleteMatcher object.
   *
   * @param \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface $selection_manager
   *   The entity reference selection handler plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity reference selection handler plugin manager.
   */
  public function __construct(SelectionPluginManagerInterface $selection_manager, EntityTypeManager $entity_type_manager) {
    parent::__construct($selection_manager);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Matches GP's to the autocomplete search string.
   */
  public function getMatches($target_type, $selection_handler, $selection_settings, $string = '') {

    if ($target_type !== 'gp') {
      return parent::getMatches($target_type, $selection_handler, $selection_settings, $string);
    }

    $matches = [];

    $options = [
      'target_type'      => $target_type,
      'handler'          => $selection_handler,
      'handler_settings' => $selection_settings,
    ];

    $handler = $this->selectionManager->getInstance($options);

    if (!empty($string) && !empty($handler)) {
      $match_operator = !empty($selection_settings['match_operator']) ? $selection_settings['match_operator'] : 'CONTAINS';
      $entity_labels = $handler->getReferenceableEntities($string, $match_operator, 30);

      foreach ($entity_labels as $values) {
        foreach ($values as $id => $label) {
          $entity = $this->entityTypeManager->getStorage($target_type)->load($id);

          $key = $label . ' (' . $id . ')';
          /** @var \Drupal\nidirect_gp\Entity\Gp $entity */
          $label .= ' [GP cypher: ' . $entity->getCypher() . '] (' . $id . ')';
          $key = Html::decodeEntities($key);
          $matches[] = ['value' => $key, 'label' => $label];
        }
      }
    }

    return $matches;
  }

}

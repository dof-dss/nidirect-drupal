<?php

namespace Drupal\nidirect_health_conditions;

use Drupal\node\NodeInterface;

class Utility {

  /**
   * Function to return Health condition node symptoms
   *
   * @param \Drupal\node\NodeInterface $node
   *   Health condition node.
   * @return array
   *   Array of symptom titles.
   */
  public function getSymptoms(NodeInterface $node) {
    $symptoms = [];

    if ($node->bundle() == 'health_condition') {
      $symptom_fields = [
        'field_hc_primary_symptom_1',
        'field_hc_primary_symptom_2',
        'field_hc_primary_symptom_3',
        'field_hc_primary_symptom_4',
        'field_hc_secondary_symptoms',
      ];

      foreach ($symptom_fields as $field_id) {
        foreach ($node->get($field_id)->referencedEntities() as $term) {
          $symptoms[] = $term->label();
        }
      }
    }

    return $symptoms;
  }

}

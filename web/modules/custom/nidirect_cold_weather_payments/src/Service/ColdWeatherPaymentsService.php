<?php

namespace Drupal\nidirect_cold_weather_payments\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides cold weather payments information.
 */
class ColdWeatherPaymentsService {

  /**
   * Drupal entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Cold Weather Payments Service constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Return payment information for a given postcode.
   *
   * @param string $postcode
   *   The postcode to check for payments.
   */
  public function forPostcode($postcode = NULL) {
    preg_match_all('/^(BT)?(\d{1,2})\s?/mi', $postcode, $matches, PREG_SET_ORDER, 0);
    $response['postcode'] = $matches[0][2];

    // Fetch the latest Payment period node.
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'cold_weather_payment')
      ->condition('status', '1')
      ->sort('created', 'DESC')
      ->accessCheck(TRUE)
      ->range(0, 1);

    $vid_keys = array_keys($query->execute());

    // Set a data key if we have no published CWP data.
    if (empty($vid_keys)) {
      $response['published_content'] = 'none';
      return $response;
    }

    // Fetch the last revision.
    $vid = array_pop($vid_keys);
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->entityTypeManager->getStorage('node')->loadRevision($vid);

    // Payment period covered.
    $period = $node->get('field_cwp_payments_period')->getValue();
    $response['id'] = $node->id();
    $response['payments_period']['date_start'] = $period[0]['value'];
    $response['payments_period']['date_end'] = $period[0]['end_value'];

    // Payment triggers for the period.
    $payment_triggers = $node->get('field_cwp_payments_triggered');
    foreach ($payment_triggers as $trigger) {
      /** @var \Drupal\Core\Entity\FieldableEntityInterface $trigger */

      $payment_granted = FALSE;
      // We need to load each station to extract the postcodes it covers.
      $station_ids = explode(',', $trigger->get('stations')->getValue());
      $stations = $this->entityTypeManager->getStorage('weather_station')->loadMultiple($station_ids);

      $postcodes = [];
      foreach ($stations as $station) {
        /** @var \Drupal\Core\Entity\FieldableEntityInterface $station */
        $station_postcodes = $station->get('postcodes') ?? '';
        $postcodes = array_merge($postcodes, explode(',', $station_postcodes));
      }

      // Postcodes for stations are stored as the first 2 digits, strip BT
      // from the query postcode.
      if (in_array(str_replace('BT', '', $response['postcode']), $postcodes)) {
        $payment_granted = TRUE;
      }

      $response['payments_triggered'][] = [
        "date_start" => $trigger->get('date_start')->getValue(),
        "date_end" => $trigger->get('date_end')->getValue(),
        "stations" => $trigger->get('stations')->getValue(),
        "postcodes" => $postcodes,
        "payment_granted" => $payment_granted,
      ];
    }

    return $response;
  }

}

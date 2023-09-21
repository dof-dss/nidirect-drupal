<?php

namespace Drupal\nidirect_cold_weather_payments;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of Weather station entities.
 */
class WeatherStationEntityListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Weather station');
    $header['postcodes'] = $this->t('Postcodes');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $row['label'] = $entity->label();
    $row['id'] = $entity->get('postcodes');
    return $row + parent::buildRow($entity);
  }

}

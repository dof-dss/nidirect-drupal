<?php

namespace Drupal\nidirect_cold_weather_payments\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Field type "nidirect_cold_weather_period".
 *
 * @FieldType(
 *   id = "nidirect_cold_weather_period",
 *   label = @Translation("Cold Weather Period"),
 *   description = @Translation("This field stores information about a cold weather payment period. Enter the start- and end-date of the period, and tick the boxes for the weather stations affected."),
 *   category = @Translation("NI Direct"),
 *   default_widget = "nidirect_cold_weather_period_default",
 *   default_formatter = "nidirect_cold_weather_period",
 * )
 */
class ColdWeatherPeriodItem extends FieldItemBase implements FieldItemInterface {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $output = [];

    $output['columns']['date_start'] = [
      'type' => 'varchar',
      'length' => 10,
    ];

    $output['columns']['date_end'] = [
      'type' => 'varchar',
      'length' => 10,
    ];

    $output['columns']['stations'] = [
      'type' => 'varchar',
      'length' => 255,
    ];

    return $output;

  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {

    $properties['date_start'] = DataDefinition::create('string')
      ->setLabel(t('Start date'))
      ->setRequired(FALSE);

    $properties['date_end'] = DataDefinition::create('string')
      ->setLabel(t('End date'))
      ->setRequired(FALSE);

    $properties['stations'] = DataDefinition::create('string')
      ->setLabel(t('Weather stations'))
      ->setRequired(FALSE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $is_dirty = FALSE;

    foreach ($this->getValue() as $input) {
      if (isset($input) && !empty($input)) {
        $is_dirty = TRUE;
      }
    }

    return !$is_dirty;
  }

}

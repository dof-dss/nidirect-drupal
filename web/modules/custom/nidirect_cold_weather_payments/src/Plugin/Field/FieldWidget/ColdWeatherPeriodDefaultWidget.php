<?php

namespace Drupal\nidirect_cold_weather_payments\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field widget "nidirect_cold_weather_period_default".
 *
 * @FieldWidget(
 *   id = "nidirect_cold_weather_period_default",
 *   label = @Translation("Cold Weather Period Default"),
 *   field_types = {
 *     "nidirect_cold_weather_period",
 *   }
 * )
 */
class ColdWeatherPeriodDefaultWidget extends WidgetBase implements WidgetInterface, ContainerFactoryPluginInterface {

  /**
   * EntityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $item =& $items[$delta];
    $date_start = $item->date_start ?? '';
    $date_end = $item->date_end ?? '';
    $item_stations = $item->stations ?? '';

    $element += [
      '#type' => 'fieldset',
      '#attributes' => ['class' => ['container-inline']],
      '#element_validate' => [[$this, 'validate']],
    ];

    $element['date_start'] = [
      '#type' => 'date',
      '#title' => t('Start date'),
      '#default_value' => $date_start,
    ];

    $element['date_end'] = [
      '#type' => 'date',
      '#title' => t('End date'),
      '#default_value' => $date_end,
    ];

    $stations = $this->entityTypeManager->getStorage('weather_station')->loadMultiple();

    foreach ($stations as $station) {
      $weather_stations[$station->id()] = $station->label();
    }

    $element['stations'] = [
      '#type' => 'checkboxes',
      '#title' => t('Weather stations'),
      '#options' => $weather_stations ?? [],
      '#default_value' => explode(',', $item_stations),
      '#description' => t('Tick the boxes for the weather stations where a cold weather payment was triggered.'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {

    foreach ($values as &$value) {
      // Remove the unchecked checkbox values.
      $stations = array_diff($value['stations'], ['0']);
      $value['stations'] = implode(',', $stations);
    }

    return $values;
  }

  /**
   * Cold weather payment triggered widget validation.
   */
  public function validate($element, FormStateInterface $form_state) {

    $date_start = $element["date_start"]["#value"];
    $date_end = $element["date_end"]["#value"];
    $stations = $element["stations"]["#value"];

    // If any part of the widget has been altered, start the validation checks.
    if (!empty($date_start) || !empty($date_end) || !empty($stations)) {

      if (empty($date_start)) {
        $form_state->setError($element["date_start"], t("You must provide a start date."));
      }

      if (empty($date_end)) {
        $form_state->setError($element["date_end"], t("You must provide a end date."));
      }

      if (!empty($date_start) && !empty($date_end)) {
        if (new \DateTime($date_start) > new \DateTime($date_end)) {
          $form_state->setError($element["date_end"], t("End date must come after the start date."));
        }
      }

      if (empty($stations) || count($stations) < 1) {
        $form_state->setError($element["stations"], t("You must provide at least one or more weather stations"));
      }
    }

  }

}

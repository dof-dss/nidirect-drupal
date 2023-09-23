<?php

namespace Drupal\nidirect_cold_weather_payments\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for creating weather station entities.
 */
class WeatherStationEntityForm extends EntityForm {

  /**
   * EntityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Class constructor.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, MessengerInterface $messenger) {
    $this->entityTypeManager = $entityTypeManager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\Core\Entity\FieldableEntityInterface $weather_station */
    $weather_station = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#maxlength' => 255,
      '#default_value' => $weather_station->label(),
      '#description' => $this->t("Name of the weather station."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $weather_station->id(),
      '#machine_name' => [
        'exists' => '\Drupal\nidirect_cold_weather_payments\Entity\WeatherStationEntity::load',
      ],
      '#disabled' => !$weather_station->isNew(),
    ];

    /* You will need additional form elements for your custom properties. */
    $form['postcodes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Postcodes'),
      '#maxlength' => 255,
      '#default_value' => $weather_station->get('postcodes'),
      '#description' => $this->t("Comma separated list of postcodes for this weather station e.g. 01,02,03"),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $postcode_exists = FALSE;
    $postcodes = explode(',', $form_state->getValue('postcodes'));
    $id = $form_state->getValue('id');
    $existing_postcodes = [];

    // Check all weather station entities for a matching postcode.
    $ids = $this->entityTypeManager->getStorage('weather_station')->getQuery()
      ->condition('id', $id, '<>')
      ->execute();
    $stations = $this->entityTypeManager->getStorage('weather_station')->loadMultiple($ids);

    foreach ($stations as $station) {
      /** @var \Drupal\Core\Entity\FieldableEntityInterface $station */
      $existing_postcodes = array_merge($existing_postcodes, explode(',', $station->get('postcodes')));
    }

    if (count(array_intersect($existing_postcodes, $postcodes)) !== 0) {
      $postcode_exists = TRUE;
    }

    if ($postcode_exists) {
      $form_state->setErrorByName('postcodes', $this->t('Postcode exists in another weather station entry.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $weather_station */
    $weather_station = $this->entity;
    $status = $weather_station->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger->addMessage($this->t('Created the weather station, %station.', [
          '%station' => $weather_station->label(),
        ]));
        break;

      default:
        $this->messenger->addMessage($this->t('Saved the weather station, %station.', [
          '%station' => $weather_station->label(),
        ]));
    }
    $form_state->setRedirectUrl($weather_station->toUrl('collection'));

    return $status;
  }

}

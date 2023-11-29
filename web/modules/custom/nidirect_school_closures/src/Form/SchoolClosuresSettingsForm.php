<?php

namespace Drupal\nidirect_school_closures\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\nidirect_school_closures\SchoolClosuresServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure example settings for this site.
 */
class SchoolClosuresSettingsForm extends ConfigFormBase {

  const SETTINGS = 'nidirect_school_closures.settings';

  /**
   * Instance of the SchoolClosureService.
   *
   * @var \Drupal\nidirect_school_closures\SchoolClosuresServiceInterface
   */
  protected $closureService;

  /**
   * Class constructor.
   */
  public function __construct(SchoolClosuresServiceInterface $closure_service) {
    $this->closureService = $closure_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('nidirect_school_closures.source.default')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'nidirect_school_closures_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [static::SETTINGS];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $form['closure_service'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Current service provider'),
      '#description' => $this->t('Defined in nidirect_school_closures.source.default'),
      '#disabled' => TRUE,
      '#default_value' => get_class($this->closureService),
    ];

    $form['data_source_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Data source URL'),
      '#description' => $this->t('URL for the school closures data.'),
      '#required' => TRUE,
      '#default_value' => $config->get('data_source_url'),
    ];

    $form['max_attempts'] = [
      '#type' => 'number',
      '#title' => $this->t('Attempts to fetch data'),
      '#description' => $this->t('The number of times to attempt fetching data from the source URL.'),
      '#required' => TRUE,
      '#min' => 1,
      '#max' => 10,
      '#size' => 2,
      '#default_value' => $config->get('max_attempts'),
    ];

    $form['cache_duration'] = [
      '#type' => 'number',
      '#title' => $this->t('Cache duration'),
      '#description' => $this->t('Duration in minutes.'),
      '#min' => 0,
      '#max' => 10080,
      '#required' => TRUE,
      '#size' => 2,
      '#default_value' => $config->get('cache_duration'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable(static::SETTINGS)
      ->set('data_source_url', $form_state->getValue('data_source_url'))
      ->set('max_attempts', $form_state->getValue('max_attempts'))
      ->set('cache_duration', $form_state->getValue('cache_duration'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}

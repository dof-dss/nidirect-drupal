<?php

namespace Drupal\nidirect_hospital_waiting_times\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure example settings for this site.
 */
class WaitingTimesSettingsForm extends ConfigFormBase {
  const SETTINGS = 'nidirect_hospital_waiting_times.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'nidirect_hospital_waiting_times_settings_form';
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

    $form['data_source_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Data source URL'),
      '#description' => $this->t('URL for the hospital waiting times data.'),
      '#required' => TRUE,
      '#default_value' => $config->get('data_source_url'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable(static::SETTINGS)
      ->set('data_source_url', $form_state->getValue('data_source_url'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}

<?php

namespace Drupal\nidirect_common\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class ProniMigrationSettingsForm extends ConfigFormBase {

  /**
   * Drupal\Core\Http\ClientFactory definition.
   *
   * @return string[]
   */
  protected function getEditableConfigNames() {
    return ['nidirect_common.proni_settings'];
  }

  /**
   * @return string
   */
  public function getFormId() {
    return 'nidirect_common_proni_settings';
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('nidirect_common.proni_settings');
    $proni_message = $this->t('<div class="info-notice">
    <h2>This content has moved</h2>
    <p>Go to <a href="https://www.proni.gov.uk" data-entity-type="external">www.proni.gov.uk</a> to find all information and services provided by the Public Records Office of Northern Ireland (PRONI).</p>
</div>');

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable PRONI Migration Message'),
      '#description' => $this->t('If checked (or if the PRONI_CONTENT_REDIRECT env var is "enabled"), anonymous users will see a message instead of content.'),
      '#default_value' => $config->get('enabled'),
    ];

    $form['message_text'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Migration Message'),
      '#format' => $config->get('message_text.format') ?? 'full_html',
      '#default_value' => $config->get('message_text.value') ?? $proni_message,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return void
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('nidirect_common.proni_settings')
      ->set('enabled', $form_state->getValue('enabled'))
      ->set('message_text', $form_state->getValue('message_text'))
      ->save();

    // Invalidate cache tags so anonymous users see the changes immediately.
    // 'config:nidirect_common.proni_settings' targets pages where the tag was added.
    // 'node_list' ensures the internal page cache is cleared for node displays.
    // 'taxonomy_term_list' ensures term page cache is cleared.
    Cache::invalidateTags([
      'config:nidirect_common.proni_settings',
      'node_list',
      'taxonomy_term_list',
    ]);

    $this->messenger()->addStatus($this->t('PRONI migration settings saved and caches invalidated.'));

    parent::submitForm($form, $form_state);
  }

}

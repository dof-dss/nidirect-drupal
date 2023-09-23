<?php

namespace Drupal\nidirect_search\Form;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Solr Elevated Id entity form.
 *
 * @property \Drupal\nidirect_search\SolrElevatedIdEntityInterface $entity
 */
class SolrElevatedIdEntityForm extends EntityForm {

  /**
   * Solr server entity.
   *
   * @var \Drupal\search_api\Entity\Server
   */
  protected $solrServer;

  /**
   * Default cache bin.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache bin.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, CacheBackendInterface $cache) {
    $this->solrServer = $entity_type_manager->getStorage('search_api_server')->load('solr_default');
    $this->cache = $cache;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('cache.default')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\nidirect_search\Entity\SolrElevatedIdEntity $entity */
    $entity = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search term'),
      '#maxlength' => 255,
      '#default_value' => $entity->label(),
      '#description' => $this->t('Search term to define elevations for.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $entity->id(),
      '#machine_name' => [
        'exists' => '\Drupal\nidirect_search\Entity\SolrElevatedIdEntity::load',
      ],
      '#disabled' => !$entity->isNew(),
    ];

    // Construct the index options by fetching the current Solr server indexes.
    $index_options = [];
    foreach ($this->solrServer->getIndexes() as $id => $index) {
      $index_options[$id] = $index->label();
    }

    $form['index'] = [
      '#type' => 'select',
      '#title' => $this->t('Search index'),
      '#options' => $index_options,
      '#default_value' => $entity->index(),
      '#description' => $this->t('Solr search index to elevate against.'),
      '#required' => TRUE,
    ];

    $form['nodes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nodes'),
      '#maxlength' => 255,
      '#default_value' => $entity->nodes(),
      '#description' => $this->t('Comma separated node ids to elevate for this search term'),
      '#placeholder' => 'e.g. 1, 1021, 67',
      '#required' => TRUE,
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => ($entity->isNew()) ? TRUE : $entity->status(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $nodes = trim($form_state->getValue('nodes'), ',');
    $error_nids = [];

    // Ensure that we're dealing with nids only.
    foreach (explode(',', $nodes) as $nid) {
      if (!is_numeric($nid)) {
        $error_nids[] = $nid;
      }
    }

    if (!empty($error_nids)) {
      $form_state->setErrorByName('nodes',
        $this->t("The following should be node id's only: @error_nids", [
          '@error_nids' => implode(',', $error_nids),
        ])
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Lower case the search term/label for query search matching.
    $form_state->setValue('label', strtolower($form_state->getValue('label')));
    $form_state->setValue('nodes', trim($form_state->getValue('nodes'), ','));

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);
    $index = '';
    $label = '';

    extract($form_state->getValues());

    $message = $result === SAVED_NEW
      ? $this->t('Created new Solr elevated ID entity: %label.', ['%label' => $this->entity->label()])
      : $this->t('Updated Solr elevated ID entity: %label.', ['%label' => $this->entity->label()]);
    $this->messenger()->addStatus($message);

    $search_cid = 'solr_elevated_id:' . $index . ':' . str_replace(' ', '_', $label);

    // Delete the cached elevated ids if entity is updated.
    if ($result === SAVED_UPDATED) {
      $this->cache->delete($search_cid);
    }

    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

}

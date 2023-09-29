<?php

namespace Drupal\nidirect_search;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of Solr Elevated ID entities.
 */
class SolrElevatedIdEntityListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'label' => [
        'data' => $this->t('Search term'),
        'field' => 'label',
        'specifier' => 'label',
      ],
      'index' => [
        'data' => $this->t('Solr index'),
        'field' => 'index',
        'specifier' => 'index',
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'status' => [
        'data' => $this->t('Status'),
        'field' => 'status',
        'specifier' => 'status',
      ],
    ];

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\nidirect_search\Entity\SolrElevatedIdEntity $entity */
    $row['label'] = $entity->label();
    $row['id'] = $entity->index();
    $row['status'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritDoc}
   */
  public function getOperations(EntityInterface $entity) {
    $operations = parent::getOperations($entity);

    if (!empty($operations['edit'])) {
      $edit = $operations['edit']['url'];
      $edit->setRouteParameters(['solr_elevated_id' => $entity->id()]);
    }

    if (!empty($operations['delete'])) {
      $edit = $operations['delete']['url'];
      $edit->setRouteParameters(['solr_elevated_id' => $entity->id()]);
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    $query = $this->getStorage()->getQuery();
    $header = $this->buildHeader();

    $query->pager(50);
    $query->tableSort($header);

    $ids = $query->execute();

    return $this->storage->loadMultiple($ids);
  }

}

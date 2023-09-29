<?php

namespace Drupal\nidirect_gp;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of GP entities.
 *
 * @ingroup nidirect_gp
 */
class GpListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('GP ID');
    $header['name'] = $this->t('GP Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /**
     * @var \Drupal\nidirect_gp\Entity\Gp $entity
     */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.gp.canonical',
      ['gp' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}

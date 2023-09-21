<?php

namespace Drupal\nidirect_breadcrumbs;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generates the breadcrumb trail for Health condition entities.
 *
 * In the format:
 * > Home
 * > Health and well-being
 * > Illnesses and conditions
 * > A to Z
 * as URL
 * > <front>
 * > information-and-services/health-and-wellbeing
 * > information-and-services/health-and-wellbeing/illnesses-and-conditions
 * > services/health-conditions-a-z.
 *
 * @package Drupal\nidirect_breadcrumbs
 */
class HealthConditionBreadcrumb implements BreadcrumbBuilderInterface {

  /**
   * Drupal entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Node object, or null if on a non-node page.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node;

  /**
   * Class constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $match = FALSE;

    $route_name = $route_match->getRouteName();

    // Full node view.
    if ($route_name == 'entity.node.canonical') {
      $this->node = $route_match->getParameter('node');
    }

    // Editorial preview.
    if ($route_name == 'entity.node.preview') {
      $this->node = $route_match->getParameter('node_preview');
    }

    if (!empty($this->node)) {

      if ($this->node instanceof NodeInterface == FALSE) {
        $this->node = $this->entityTypeManager->getStorage('node')->load($this->node);
      }

      if (!empty($this->node)) {
        $match = $this->node->bundle() == 'health_condition';
      }
    }

    if ($route_name == 'view.health_conditions.search_page') {
      // Also match on health condition search page.
      $match = TRUE;
    }

    if ($route_name == 'nidirect_health_conditions.letter') {
      // Also match on health condition a-z page.
      $match = TRUE;
    }

    return $match;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {

    $breadcrumb = new Breadcrumb();
    $links[] = Link::createFromRoute(t('Home'), '<front>');
    $links[] = Link::fromTextandUrl(t('Health and wellbeing'), Url::fromUri('entity:taxonomy_term/22'));
    $links[] = Link::fromTextandUrl(t('Illnesses and conditions'), Url::fromUri('entity:node/7387'));

    if ($this->node) {
      $links[] = Link::fromTextandUrl(t('A to Z'), Url::fromRoute('view.health_conditions.search_page'));
    }

    $breadcrumb->setLinks($links);
    $breadcrumb->addCacheContexts(['url.path']);

    // Add cache tags so that if any entities above change, we can
    // regenerate the breadcrumb too.
    $breadcrumb->addCacheTags([
      'taxonomy_term:22',
      'node:7387',
    ]);

    return $breadcrumb;
  }

}

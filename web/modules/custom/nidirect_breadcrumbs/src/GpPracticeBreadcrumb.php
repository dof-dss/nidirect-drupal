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
 * Generates the breadcrumb trail for GP Practice entities.
 *
 * In the format:
 * > Home
 * > Health and well-being
 * > Health services
 * > Doctors, dentists and other health services
 * > Find a GP practice
 * as URL:
 * > <front>
 * > information-and-services/health-and-well-being
 * > information-and-services/health-and-well-being/health-services
 * > information-and-services/health-services/doctors-dentists-and-other-health-services
 * > services/gp-practices.
 *
 * @package Drupal\nidirect_breadcrumbs
 */
class GpPracticeBreadcrumb implements BreadcrumbBuilderInterface {

  /**
   * Core EntityTypeManager instance.
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
        $match = $this->node->bundle() == 'gp_practice';
      }
    }

    if ($route_name == 'nidirect_gp.gp_search') {
      // Also match on GP search page.
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
    $links[] = Link::fromTextandUrl(t('Health and well-being'), Url::fromUri('entity:taxonomy_term/22'));
    $links[] = Link::fromTextandUrl(t('Health services'), Url::fromUri('entity:taxonomy_term/262'));
    $links[] = Link::fromTextandUrl(t('Doctors, dentists and other health services'), Url::fromUri('entity:taxonomy_term/263'));
    $links[] = Link::fromTextandUrl(
      t('Find a GP practice'), Url::fromRoute(($this->node) ? 'nidirect_gp.gp_search' : '<none>')
    );

    $breadcrumb->setLinks($links);
    $breadcrumb->addCacheContexts(['url.path']);

    // Add cache tags so that if any entities above change, we can regenerate
    // the breadcrumb too.
    $breadcrumb->addCacheTags([
      'taxonomy_term:22',
      'taxonomy_term:262',
      'taxonomy_term:263',
    ]);

    return $breadcrumb;
  }

}

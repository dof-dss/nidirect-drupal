<?php

namespace Drupal\nidirect_breadcrumbs;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generates the breadcrumb trail for Contact entities.
 *
 * Breadcrumb format:
 * Home > Contacts as URL <front> > contacts.
 *
 * @package Drupal\nidirect_breadcrumbs
 */
class ContactBreadcrumb implements BreadcrumbBuilderInterface {

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
        $match = $this->node->bundle() == 'contact';
      }
    }

    // Also check for contacts listing/search pages.
    if (in_array($route_name, [
      'nidirect_contacts.default',
      'nidirect_contacts.letter',
    ])) {
      $match = TRUE;
    }

    return $match;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();

    $links = [];

    if (!empty($this->node)) {
      // Only add to contact node pages.
      $links[] = Link::createFromRoute(t('Home'), '<front>');
      $links[] = Link::createFromRoute(t('Contacts'), 'nidirect_contacts.default');
    }

    $breadcrumb->setLinks($links);
    $breadcrumb->addCacheContexts(['url.path']);

    return $breadcrumb;
  }

}

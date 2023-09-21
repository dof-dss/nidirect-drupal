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
 * Generates the breadcrumb trail for Landing pages.
 *
 * @package Drupal\nidirect_breadcrumbs
 */
class LandingPageBreadcrumb implements BreadcrumbBuilderInterface {

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
        $match = $this->node->bundle() == 'landing_page';
      }
    }

    return $match;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {

    $breadcrumb = new Breadcrumb();

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

      if (isset($this->node->field_subtheme)) {
        $subtheme_tid = $this->node->field_subtheme->target_id;

        // This issue https://www.drupal.org/node/2019905
        // prevents us from using ->loadParents() as we won't
        // retrieve the root term.
        $ancestors = array_values($this->entityTypeManager->getStorage("taxonomy_term")->loadAllParents($subtheme_tid));

        // Remove the current term from the list.
        array_shift($ancestors);

        $links[] = Link::createFromRoute(t('Home'), '<front>');

        if (!empty($ancestors)) {
          $terms = (count($ancestors) > 1) ? array_reverse($ancestors, TRUE) : $ancestors;

          foreach ($terms as $term) {
            $links[] = Link::fromTextandUrl($term->label(), Url::fromUri('entity:taxonomy_term/' . $term->id()));
            $cache_tags[] = 'taxonomy_term:' . $term->id();
          }
        }

        // Assemble a new breadcrumb object, add the links and set
        // a URL path cache context so it varies as you move from one
        // set of content to another.
        $breadcrumb = new Breadcrumb();
        $breadcrumb->setLinks($links);
        $breadcrumb->addCacheContexts(['url.path']);

        if (!empty($cache_tags)) {
          $breadcrumb->addCacheTags($cache_tags);
        }
      }
    }

    return $breadcrumb;
  }

}

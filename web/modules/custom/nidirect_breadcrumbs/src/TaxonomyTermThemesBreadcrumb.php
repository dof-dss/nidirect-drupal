<?php

namespace Drupal\nidirect_breadcrumbs;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generates the breadcrumb trail for Taxonomy term pages.
 *
 * @package Drupal\nidirect_breadcrumbs
 */
class TaxonomyTermThemesBreadcrumb implements BreadcrumbBuilderInterface {

  /**
   * Core EntityTypeManager instance.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Taxonomy term object.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  protected $term;

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

    if ($route_name == 'entity.taxonomy_term.canonical') {
      $match = TRUE;
    }

    return $match;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {

    $cache_tags = [];
    $links = [];

    $this->term = $route_match->getParameter('taxonomy_term');

    // This issue https://www.drupal.org/node/2019905
    // prevents us from using ->loadParents() as we won't
    // retrieve the root term.
    $ancestors = array_values($this->entityTypeManager->getStorage("taxonomy_term")->loadAllParents($this->term->id()));

    // Remove the current term from the list.
    array_shift($ancestors);

    if (!empty($ancestors)) {

      $links[] = Link::createFromRoute(t('Home'), '<front>');

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

    return $breadcrumb;
  }

}

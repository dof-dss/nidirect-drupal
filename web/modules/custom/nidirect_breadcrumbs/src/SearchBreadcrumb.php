<?php

namespace Drupal\nidirect_breadcrumbs;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generates the breadcrumb trail for search page(s).
 *
 * @package Drupal\nidirect_breadcrumbs
 */
class SearchBreadcrumb implements BreadcrumbBuilderInterface {

  /**
   * Route matches from the service container parameters.
   *
   * @var array
   */
  protected $routeMatches;

  /**
   * Class constructor.
   */
  public function __construct(array $route_matches) {
    $this->routeMatches = $route_matches;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('breadcrumb.search.matches')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $match = FALSE;

    if (in_array($route_match->getRouteName(), $this->routeMatches)) {
      $match = TRUE;
    }

    return $match;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {

    $breadcrumb = new Breadcrumb();
    $breadcrumb->setLinks([]);
    $breadcrumb->addCacheContexts(['url.path']);

    return $breadcrumb;
  }

}

<?php

namespace Drupal\nidirect_breadcrumbs;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Returns a null breadcrumb trail for any matched paths.
 *
 * @package Drupal\nidirect_breadcrumbs
 */
class NullBreadcrumb implements BreadcrumbBuilderInterface {

  /**
   * Route matches from the service container parameters.
   *
   * @var array
   */
  protected $routeMatches;

  /**
   * Request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Class constructor.
   *
   * @param array $route_matches
   *   Matching route/URL paths.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Request stack object/service.
   */
  public function __construct(array $route_matches, RequestStack $request_stack) {
    $this->routeMatches = $route_matches;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('breadcrumb.null_breadcrumb.matches'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $match = FALSE;

    // Check for matching paths.
    if (in_array($this->requestStack->getCurrentRequest()->getPathInfo(), $this->routeMatches)) {
      $match = TRUE;
    }

    // Check for matching route names.
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

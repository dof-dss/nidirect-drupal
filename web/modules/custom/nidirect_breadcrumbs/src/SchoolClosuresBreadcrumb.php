<?php

namespace Drupal\nidirect_breadcrumbs;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Generates the breadcrumb trail for School closures.
 *
 * @package Drupal\nidirect_breadcrumbs
 */
class SchoolClosuresBreadcrumb implements BreadcrumbBuilderInterface {

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
      $container->get('breadcrumb.schoolclosures.matches'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $match = FALSE;

    if (in_array($this->requestStack->getCurrentRequest()->getPathInfo(), $this->routeMatches)) {
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
    // TODO: replace fixed text/paths with routes to actual nodes or
    // taxonomy term pages.
    $links[] = Link::fromTextAndUrl('Education', Url::fromUserInput('/information-and-services/education'));
    $links[] = Link::fromTextAndUrl('Schools, learning and development', Url::fromUserInput('/information-and-services/education/schools-learning-and-development'));
    $links[] = Link::fromTextAndUrl('School life', Url::fromUserInput('/information-and-services/schools-learning-and-development/school-life'));
    $breadcrumb->setLinks($links);
    $breadcrumb->addCacheContexts(['url.path']);

    return $breadcrumb;
  }

}

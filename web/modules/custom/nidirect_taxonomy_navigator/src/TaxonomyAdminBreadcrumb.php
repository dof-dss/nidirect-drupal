<?php

namespace Drupal\nidirect_taxonomy_navigator;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Link;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\system\PathBasedBreadcrumbBuilder;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;

/**
 * Rewrites the core taxonomy breadcrumb for a more consistent UX when
 * using the taxonomy_navigator module.
 *
 * Extends the core path based breadcrumb builder but slightly
 * adjusts how the trail is assembled; see build() below.
 *
 * @package Drupal\nidirect_taxonomy_navigator
 */
class TaxonomyAdminBreadcrumb extends PathBasedBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  /**
   * The router request context.
   *
   * @var \Drupal\Core\Routing\RequestContext
   */
  protected $context;

  /**
   * The menu link access service.
   *
   * @var \Drupal\Core\Access\AccessManagerInterface
   */
  protected $accessManager;

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
   * Constructs the TaxonomyAdminBreadcrumb.
   *
   * @param \Drupal\Core\Routing\RequestContext $context
   *   The router request context.
   * @param \Drupal\Core\Access\AccessManagerInterface $access_manager
   *   The menu link access service.
   * @param \Symfony\Component\Routing\Matcher\RequestMatcherInterface $router
   *   The dynamic router service.
   * @param \Drupal\Core\PathProcessor\InboundPathProcessorInterface $path_processor
   *   The inbound path processor.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Controller\TitleResolverInterface $title_resolver
   *   The title resolver service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user object.
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   *   The current path.
   * @param \Drupal\Core\Path\PathMatcherInterface $path_matcher
   *   The path matcher service.
   * @param array $route_matches
   *   Matching routes injected from service parameters.
   */
  public function __construct(RequestContext $context, AccessManagerInterface $access_manager, RequestMatcherInterface $router, InboundPathProcessorInterface $path_processor, ConfigFactoryInterface $config_factory, TitleResolverInterface $title_resolver, AccountInterface $current_user, CurrentPathStack $current_path, PathMatcherInterface $path_matcher = NULL, array $route_matches) {
    parent::__construct($context, $access_manager, $router, $path_processor, $config_factory, $title_resolver, $current_user, $current_path, $path_matcher);
    $this->routeMatches = $route_matches;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $match = FALSE;

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
    $links = [];

    // General path-based breadcrumbs. Use the actual request path, prior to
    // resolving path aliases, so the breadcrumb can be defined by simply
    // creating a hierarchy of path aliases.
    $path = trim($this->context->getPathInfo(), '/');
    $path_elements = explode('/', $path);
    $exclude = [];

    // Intercept/replace the core taxonomy route with our taxonomy navigator equivalent.
    foreach ($path_elements as $index => $element) {
      if ($element == 'taxonomy') {
        $path_elements[$index] = 'taxonomy_navigator';
      }

      // Drop the redundant manage path element (from core).
      if ($element == 'manage') {
        unset($path_elements[$index]);
      }
    }

    while (count($path_elements) > 1) {
      array_pop($path_elements);

      // Copy the path elements for up-casting.
      $route_request = $this->getRequestForPath('/' . implode('/', $path_elements), $exclude);

      if ($route_request) {
        $route_match = RouteMatch::createFromRequest($route_request);
        $access = $this->accessManager->check($route_match, $this->currentUser, NULL, TRUE);
        // The set of breadcrumb links depends on the access result, so merge
        // the access result's cacheability metadata.
        $breadcrumb = $breadcrumb->addCacheableDependency($access);
        if ($access->isAllowed()) {
          $title = $this->titleResolver->getTitle($route_request, $route_match->getRouteObject());
          if (!isset($title)) {
            // Fallback to using the raw path component as the title if the
            // route is missing a _title or _title_callback attribute.
            $title = str_replace(['-', '_'], ' ', Unicode::ucfirst(end($path_elements)));
          }
          $url = Url::fromRouteMatch($route_match);
          $links[] = new Link($title, $url);
        }
      }
    }
    // Add the Home link.
    $links[] = Link::createFromRoute($this->t('Home'), '<front>');

    return $breadcrumb->setLinks(array_reverse($links));
  }

}

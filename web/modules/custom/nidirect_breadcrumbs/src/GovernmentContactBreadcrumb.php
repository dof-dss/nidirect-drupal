<?php

namespace Drupal\nidirect_breadcrumbs;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Generates the breadcrumb trail for a few ad-hoc pages.
 *
 * See the parameters section of the services.yml file
 * for a list of routes matched to this breadcrumb pattern.
 *
 * @package Drupal\nidirect_breadcrumbs
 */
class GovernmentContactBreadcrumb implements BreadcrumbBuilderInterface {

  /**
   * Drupal entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Route match parameters.
   *
   * @var array
   */
  protected $routeMatches;

  /**
   * Request stack service object.
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
      $container->get('breadcrumb.contacts_govt.matches'),
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

    $links = [];
    $links[] = Link::createFromRoute(t('Home'), '<front>');
    $links[] = Link::createFromRoute(t('Contacts'), 'nidirect_contacts.default');

    $breadcrumb->setLinks($links);
    $breadcrumb->addCacheContexts(['url.path']);

    return $breadcrumb;
  }

}

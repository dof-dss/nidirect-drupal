<?php

namespace Drupal\nidirect_taxonomy_navigator\Plugin\Menu\LocalAction;

use Drupal\Core\Menu\LocalActionDefault;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates a local action linking to the current taxonomy add term form.
 */
class AddTermLocalAction extends LocalActionDefault {

  /**
   * Current route match.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * Constructs a MenuLinkAdd object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider to load routes by name.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $route_match
   *   The current route match object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteProviderInterface $route_provider, CurrentRouteMatch $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $route_provider);

    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('router.route_provider'),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteParameters(RouteMatchInterface $route_match) {
    $vocabulary = $this->routeMatch->getParameter('vocabulary');
    $route_parameters = parent::getRouteParameters($route_match);
    $route_parameters['taxonomy_vocabulary'] = $vocabulary->id();

    return $route_parameters;
  }

}

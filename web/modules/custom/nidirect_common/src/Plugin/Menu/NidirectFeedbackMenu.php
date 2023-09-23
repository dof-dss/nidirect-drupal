<?php

namespace Drupal\nidirect_common\Plugin\Menu;

use Drupal\Core\Menu\MenuLinkDefault;
use Drupal\Core\Menu\StaticMenuLinkOverridesInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a default implementation for menu link plugins.
 */
class NidirectFeedbackMenu extends MenuLinkDefault {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new LoginLogoutMenuLink.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Menu\StaticMenuLinkOverridesInterface $static_override
   *   The static override storage.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, StaticMenuLinkOverridesInterface $static_override, RequestStack $request_stack) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $static_override);

    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('menu_link.static.overrides'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions() {
    $options = parent::getOptions();

    // Append the current path as 's' parameter.
    // $page = \Drupal::request()->getRequestUri();
    $page = $this->requestStack->getCurrentRequest()->getRequestUri();
    if ($page == '/') {
      // Homepage is a special case.
      $page = '/front';
    }
    $options['query']['s'] = $page;
    return $options;
  }

  /**
   * Cache this menu entry as much as possible.
   */
  public function getCacheContexts() {
    return ['url.path'];
  }

}

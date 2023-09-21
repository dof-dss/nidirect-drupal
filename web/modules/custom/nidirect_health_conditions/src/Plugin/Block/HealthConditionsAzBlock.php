<?php

namespace Drupal\nidirect_health_conditions\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'HealthConditionsAzBlock' block.
 *
 * This block is used instead of a views exposed filter, because we use an
 * embed display type to allow us a non-AJAX based block form. Views would
 * provide this but requires AJAX for it to operate, losing us the
 * behaviour we want.
 *
 * @Block(
 *  id = "healthconditions_az_block",
 *  admin_label = @Translation("Health Conditions A to Z"),
 * )
 */
class HealthConditionsAzBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * \Drupal\Core\Routing\CurrentRouteMatch definition.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * Constructor to inject services.
   *
   * @param array $configuration
   *   Site configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $route_match
   *   Route match object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CurrentRouteMatch $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $links = [];

    $letter = $this->routeMatch->getParameter('letter');

    foreach (range('a', 'z') as $item) {
      if ($item == $letter) {
        $links[] = [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => [
            'class' => ['a-nolink', 'active'],
          ],
          '#value' => strtoupper($item),
        ];
      }
      else {
        $links[] = Link::createFromRoute(strtoupper($item), 'nidirect_health_conditions.letter', ['letter' => $item], [
          'attributes' => [
            'title' => $this->t('View entries under :item', [':item' => strtoupper($item)]),
          ],
        ])->toRenderable();
      }
    }

    $build['healthconditions_az_block'] = [
      '#theme' => 'item_list',
      '#items' => $links,
      '#attributes' => [
        'class' => 'az-facet-list',
        'id' => 'health-conditions-az',
      ],
      '#cache' => [
        'contexts' => [
          'url.path',
        ],
      ],
    ];

    return $build;
  }

}

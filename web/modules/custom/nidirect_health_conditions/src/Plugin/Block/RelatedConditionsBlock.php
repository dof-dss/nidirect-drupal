<?php

namespace Drupal\nidirect_health_conditions\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Related Conditions block.
 *
 * Displays a list of links comprising of:
 * - Parent condition (field_parent_condition)
 * - One or more related conditions (field_related_condition)
 *
 * This block is used over a views Block display as the cardinality and
 * structure of the data model makes it easier to extra/combine using
 * custom code over extensive views + preprocess hooks.
 *
 * @Block(
 *  id = "healthconditions_related_conditions",
 *  admin_label = @Translation("Health Conditions: Related conditions"),
 * )
 */
class RelatedConditionsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * \Drupal\Core\Routing\CurrentRouteMatch definition.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Node to use.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CurrentRouteMatch $route_match, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
    $this->entityTypeManager = $entity_type_manager;

    if (!empty($configuration['node'])) {
      $this->node = $configuration['node'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $content = [];
    $links = [];
    $storage = $this->entityTypeManager->getStorage('node');

    // Load the node entity from the current page/block config. This block will
    // be configured to only show in contexts where this is available.
    if ($this->node instanceof NodeInterface == FALSE) {
      $route_name = $this->routeMatch->getRouteName();

      // Fetch node from Full (canonical) or preview display.
      if ($route_name == 'entity.node.canonical') {
        $this->node = $this->routeMatch->getParameter('node');
      }
      elseif ($route_name == 'entity.node.preview') {
        $this->node = $this->routeMatch->getParameter('node_preview');
      }
      else {
        return [];
      }

    }

    // But check it's what we're expecting anyway.
    if (!empty($this->node) && $this->node->hasField('field_parent_condition') && $this->node->hasField('field_related_conditions')) {
      // Load the parent condition field first.
      $parent_condition_nid = $this->node->get('field_parent_condition')->target_id;

      if (!empty($parent_condition_nid)) {
        $parent_condition = $storage->load($parent_condition_nid);
        $links[] = Link::createFromRoute(
          $parent_condition->label(),
          'entity.node.canonical',
          ['node' => $parent_condition_nid]
        );
      }

      // Get related conditions and add to links array if we have values.
      $related_conditions = $this->node->get('field_related_conditions')->getValue();

      if (count((array) $related_conditions) > 0) {
        foreach ($related_conditions as $condition) {
          $condition_node = $storage->load($condition['target_id']);

          $links[] = Link::createFromRoute(
            $condition_node->label(),
            'entity.node.canonical',
            ['node' => $condition_node->id()]
          );
        }
      }

      // Prevent the block title from displaying if we don't have any links.
      if (empty($links)) {
        return [
          '#cache' => [
            'contexts' => [
              'url.path',
            ],
          ],
        ];
      }

      $content['related_conditions'] = [
        '#theme' => 'item_list__related_condition_list',
        '#items' => $links,
        '#attributes' => [
          'class' => 'related-condition-list',
        ],
        '#cache' => [
          'contexts' => [
            'url.path',
          ],
        ],
      ];
    }

    return $content;
  }

}

<?php

namespace Drupal\nidirect_homepage\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use function t;

/**
 * Provides a block to show a single featured content list.
 *
 * @Block(
 *  id = "featured_content",
 *  admin_label = @Translation("Featured content"),
 * )
 */
class FeaturedContentBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new featured content block.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(array $configuration, string $plugin_id, array $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $config = $this->getConfiguration();

    $featured_items = $config['featured_items'];

    if (!empty($featured_items)) {
      // Load the first featured content list node.
      $fcl_nodes = $this->entityTypeManager->getStorage('node')->loadMultiple([$featured_items]);

      foreach ($fcl_nodes as $nid) {
        $node_render = $this->entityTypeManager->getViewBuilder('node')->view($nid);
        // Add some metadata so we can hide the teaser field when preprocessing feature nodes.
        $node_render['#hide_feature_fields'] = ['field_teaser'];
        $build['featured_content'][] = $node_render;
      }
    }

    $build['#attributes']['class'] = ['section--featured-highlights'];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();

    $fcl_query = $this->entityTypeManager->getStorage('node')->loadByProperties(
      ['type' => 'featured_content_list'],
    );

    $fcl_nodes = [];

    foreach ($fcl_query as $fcl_node) {
      $fcl_nodes[$fcl_node->id()] = $fcl_node->label();
    }

    $form['featured_items'] = [
      '#title' => t('Featured items'),
      '#type' => 'select',
      '#options' => $fcl_nodes,
      '#default_value' => !empty($config['featured_items']) ? $config['featured_items'] : '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    $this->configuration['featured_items'] = $values['featured_items'];
  }

}

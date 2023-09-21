<?php

namespace Drupal\nidirect_related_content\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\nidirect_related_content\RelatedContentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a related content block block.
 *
 * @Block(
 *   id = "nidirect_related_content_block",
 *   admin_label = @Translation("Related content block"),
 *   category = @Translation("NIDirect")
 * )
 */
class RelatedContentBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Related content manager.
   *
   * @var \Drupal\nidirect_related_content\RelatedContentManager
   */
  protected $relatedContentManager;

  /**
   * Constructs a new RelatedContentBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\nidirect_related_content\RelatedContentManager $related_content_manager
   *   The related content manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, RelatedContentManager $related_content_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->relatedContentManager = $related_content_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('nidirect_related_content.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $content = $this->relatedContentManager
      ->getSubThemesAndNodes()
      ->forNode()
      ->asRenderArray();

    $build = $content;

    return $build;
  }

}

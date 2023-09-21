<?php

namespace Drupal\nidirect_common;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\metatag\MetatagTagPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ViewsMetatagManager {

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Metatag plugin manager for tags.
   *
   * @var \Drupal\metatag\MetatagTagPluginManager
   */
  protected $metatagTagPluginManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager,
                              MetatagTagPluginManager $metatag_tag_plugin_manager) {

    $this->entityTypeManager = $entity_type_manager;
    $this->metatagTagPluginManager = $metatag_tag_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.metatag.tag')
    );
  }

  /**
   * Function to return an array of metatags for a given views display.
   *
   * @param string $view_id
   *   The view machine id.
   * @param string $display_id
   *   The display id for the view.
   * @return array|null
   *   The returned metatags for the display specified.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getMetatagsForView(string $view_id, string $display_id) {
    $view = $this->entityTypeManager
      ->getStorage('view')
      ->load($view_id)
      ->getExecutable();

    $tags = metatag_views_get_view_tags($view, $display_id);
    return $tags;
  }

  /**
   * Convenience function to append metatags to the page head.
   *
   * @param array $content
   *   Page render array.
   * @param array $tags
   *   Metatags to process.
   * @return array
   *   The modified render array.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function addTagsToPageRender(array $content, array $tags) {
    if (!empty($tags)) {
      foreach ($tags as $name => $value) {
        $tag_plugin = $this->metatagTagPluginManager->getDefinition($name);

        $tag = [
          '#type' => 'html_tag',
          '#tag' => 'meta',
          '#attributes' => [
            'name' => $tag_plugin['name'],
            'content' => $value,
          ],
        ];

        $content['#attached']['html_head'][] = [$tag, $tag_plugin['name']];
      }
    }

    return $content;
  }

}

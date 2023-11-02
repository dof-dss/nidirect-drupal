<?php

namespace Drupal\nidirect_common;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Utility class to invalidates theme taxonomy cache tags.
 */
class InvalidateTaxonomyListCacheTags {

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new \Drupal\Core\Menu\MenuTreeStorage.
   *
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tags_invalidator
   *   The cache tags invalidator.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(CacheTagsInvalidatorInterface $cache_tags_invalidator,
                              EntityTypeManagerInterface $entity_type_manager) {
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Invalidate custom cache tags for this entity.
   */
  public function invalidateForEntity(EntityInterface $entity) {
    /** @var \Drupal\taxonomy\TermInterface $entity */
    // If a node references any point in the 'site themes'
    // vocabulary, make sure that the appropriate taxonomy
    // cache tags are invalidated.
    $field_list = ['field_subtheme', 'field_site_themes'];
    $taxonomy_tags = [];
    foreach ($field_list as $thisfield) {
      if ($entity->hasField($thisfield)) {
        $tid = $entity->get($thisfield)->target_id;
        $taxonomy_tags[] = 'taxonomy_term_list:' . $tid;

        // If landing page, get parent as well.
        if (!empty($tid)) {
          $tid = $this->checkLandingPageParent($entity, $tid);
          $taxonomy_tags[] = 'taxonomy_term_list:' . $tid;
        }
        // If this is an update, invalidate cache tag for
        // original taxonomy term as well.
        /** @var \Drupal\Core\Entity\ContentEntityInterface $original */
        $original = $entity->original ?? '';
        if ($original instanceof NodeInterface) {
          $tid = $original->get($thisfield)->target_id ?? 0;
          if (!empty($tid)) {
            $taxonomy_tags[] = 'taxonomy_term_list:' . $tid;
            $parent_tid = $this->checkLandingPageParent($entity, $tid);

            if (!empty($parent_tid)) {
              $taxonomy_tags[] = 'taxonomy_term_list:' . $parent_tid;
            }
          }
        }
      }
    }

    if (count($taxonomy_tags) > 0) {
      $this->cacheTagsInvalidator->invalidateTags($taxonomy_tags);
    }
  }

  /**
   * Get parent taxonomy term for landing pages.
   */
  private function checkLandingPageParent(EntityInterface $entity, int $tid) {
    /** @var \Drupal\taxonomy\TermInterface $entity */
    // Landing page is a special case.
    if ($entity->get('type')->target_id === 'landing_page') {
      // In this case we want to invalidate the cache tag for
      // the parent of the current term.
      $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($tid);
      if (isset($term->parent->target_id)) {
        $tid = $term->parent->target_id;
      }
    }
    return $tid;
  }

}

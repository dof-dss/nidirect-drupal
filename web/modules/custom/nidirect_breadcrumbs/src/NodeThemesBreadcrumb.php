<?php

namespace Drupal\nidirect_breadcrumbs;

use Drupal\book\BookManagerInterface;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generates the breadcrumb trail for content types with subtheme fields.
 *
 * Applies to the following:
 * - Article
 * - Application
 * - Landing page
 * - Publications
 * - Webforms
 * content types.
 *
 * @package Drupal\nidirect_breadcrumbs
 */
class NodeThemesBreadcrumb implements BreadcrumbBuilderInterface {

  /**
   * Core EntityTypeManager instance.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Node object, or null if on a non-node page.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node;

  /**
   * Core BookManager instance.
   *
   * @var \Drupal\book\BookManagerInterface
   */
  protected $bookManager;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   *
   * @param \Drupal\book\BookManagerInterface $book_manager
   *   The book manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, BookManagerInterface $book_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->bookManager = $book_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('book.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $match = FALSE;

    $route_name = $route_match->getRouteName();

    // Full node view or webform confirmation.
    if (($route_name == 'entity.node.canonical')
      || ($route_name == 'entity.node.webform.confirmation')) {
      $this->node = $route_match->getParameter('node');
    }

    // Editorial preview.
    if ($route_name == 'entity.node.preview') {
      $this->node = $route_match->getParameter('node_preview');
    }

    if (!empty($this->node)) {

      if ($this->node instanceof NodeInterface == FALSE) {
        // Node route but needs loaded entity to check bundle.
        $this->node = $this->entityTypeManager->getStorage('node')->load($this->node);
      }

      if (!empty($this->node)) {
        $applies_to_types = [
          'application',
          'article',
          'embargoed_publication',
          'external_link',
          'feature',
          'featured_content_list',
          'landing_page',
          'publication',
          'webform',
        ];

        $match = in_array($this->node->bundle(), $applies_to_types);
      }
    }

    return $match;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {

    $breadcrumb = new Breadcrumb();
    $links = [];
    $cache_tags = [];

    // Fetch the node or preview node object.
    $node = $route_match->getParameter('node') ?? $route_match->getParameter('node_preview');

    // Return early if it's a supporting/secondary node type:
    // feature/featured_content_list.
    if (preg_match('/^feature/', $node->getType())) {
      return $breadcrumb;
    }

    if ($node->hasField('field_subtheme') && !empty($node->field_subtheme->target_id)) {
      $links[] = Link::createFromRoute(t('Home'), '<front>');

      $theme_tid = $node->field_subtheme->target_id;

      // Find parent terms, if any and begin to build up link chain.
      $ancestors = $this->entityTypeManager->getStorage("taxonomy_term")->loadAllParents($theme_tid);
      // Flip so we have oldest > youngest ancestors.
      $ancestors = array_reverse($ancestors, TRUE);

      foreach ($ancestors as $term) {
        $links[] = Link::fromTextandUrl($term->label(), Url::fromUri('entity:taxonomy_term/' . $term->id()));
        $cache_tags[] = 'taxonomy_term:' . $term->id();
      }
    }

    // Determine if node is part of a book and add link(s) to its
    // book parent(s).
    if (!empty($node->book)) {

      // Determine depth of node in the book.
      $book_depth = $node->book['depth'];

      // Create links to parent nodes in the depths above - if published.
      $i = 1;
      while ($i < $book_depth) {
        $p = 'p' . $i++;
        $query = $this->entityTypeManager->getStorage('node')->getQuery();

        $nid_published = $query->condition('nid', $node->book[$p], '=')
          ->condition('status', NodeInterface::PUBLISHED)
          ->accessCheck(TRUE)
          ->execute();

        if ($nid_published && $parent = $this->bookManager->loadBookLink($node->book[$p])) {
          $links[] = Link::fromTextandUrl($parent['title'], Url::fromUri('entity:node/' . $node->book[$p]));
        }
      }
    }

    // Assemble a new breadcrumb object, add the links and set
    // a URL path cache context so it varies as you move from one
    // set of content to another.
    $breadcrumb->setLinks($links);
    $breadcrumb->addCacheContexts(['url.path']);

    // Prevent the caching of breadcrumbs on updated content and node previews.
    if ($route_match->getRouteName() === 'entity.node.preview') {
      // Node previews don't have a Node ID we can reference but instead use a
      // UUID that is present in the preview url path. Using this UUID we can
      // build a cache tag that can invalidated on the preview form submit
      // handler: nidirect_breadcrumbs_preview_cache_handler().
      $url_path = \Drupal::request()->getPathInfo();
      $paths = explode('/', $url_path);
      $cache_tags[] = 'node:' . $paths[3];
    }
    else {
      // Invalidate the breadcrumb cache if the node is updated.
      $cache_tags[] = 'node:' . $node->id();
    }

    $breadcrumb->addCacheTags($cache_tags);

    return $breadcrumb;
  }

}

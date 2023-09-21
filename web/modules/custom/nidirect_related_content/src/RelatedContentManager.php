<?php

namespace Drupal\nidirect_related_content;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Url;
use Drupal\flag\FlagService;
use Drupal\taxonomy\TermInterface;

/**
 * Provides methods for managing related content display.
 *
 * @package Drupal\nidirect_related_content
 */
class RelatedContentManager {

  protected const CONTENT_ALL = 'all';
  protected const CONTENT_THEMES = 'themes';
  protected const CONTENT_NODES = 'nodes';

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current route.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * Drupal renderer service.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * Flag module service.
   *
   * @var \Drupal\flag\FlagService
   */
  protected $flagService;

  /**
   * Theme/term id.
   *
   * @var int
   *   ID of the term to return results for.
   */
  protected $termId;

  /**
   * Content types to retrieve.
   *
   * @var string
   *   Array of theme content.
   */
  protected $returnContentTypes;

  /**
   * Cache tags for render array.
   *
   * @var array
   *   String array of cache tags.
   */
  protected $cacheTags;

  /**
   * Content array.
   *
   * @var array
   *   Array of related content.
   */
  protected $content;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity query manager.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $route_match
   *   Current route match.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   Drupal renderer.
   * @param \Drupal\flag\FlagService $flag
   *   Flag module service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, CurrentRouteMatch $route_match, Renderer $renderer, FlagService $flag) {
    $this->entityTypeManager = $entity_type_manager;
    $this->routeMatch = $route_match;
    $this->renderer = $renderer;
    $this->flagService = $flag;
    $this->content = [];
  }

  /**
   * Set the query to return subthemes and nodes.
   *
   * @return $this
   */
  public function getSubThemesAndNodes() {
    $this->returnContentTypes = self::CONTENT_ALL;
    return $this;
  }

  /**
   * Set the query to return subthemes.
   *
   * @return $this
   */
  public function getSubThemes() {
    $this->returnContentTypes = self::CONTENT_THEMES;
    return $this;
  }

  /**
   * Set the query to return nodes.
   *
   * @return $this
   */
  public function getNodes() {
    $this->returnContentTypes = self::CONTENT_NODES;
    return $this;
  }

  /**
   * Term id to retrieve content for.
   *
   * @param int|null $term_id
   *   Theme term_id or null to retrieve the requested page term.
   *
   * @return $this
   */
  public function forTheme(int $term_id = NULL) {
    // If term_id isn't passed in try and extract from the current request.
    if ($term_id === NULL && $this->routeMatch->getRouteName() === 'entity.taxonomy_term.canonical') {
      $this->termId = (int) $this->routeMatch->getRawParameter('taxonomy_term');
    }
    else {
      $this->termId = $term_id;
    }

    $this->getContent();
    return $this;
  }

  /**
   * Node id to retrieve term content for.
   *
   * @param int|null $node_id
   *   Node id or null to retrieve the requested page node.
   *
   * @return $this
   */
  public function forNode(int $node_id = NULL) {

    if ($node_id === NULL) {
      $route_name = $this->routeMatch->getRouteName();

      // Ensure we're only dealing with node entity routes.
      if (strpos($route_name, 'entity.node.') !== 0) {
        return $this;
      }

      if ($route_name === 'entity.node.preview') {
        $node = \Drupal::routeMatch()->getParameter('node_preview');
      }
      else {
        // Use the raw value as some node routes have the entity object and
        // others only pass the id.
        $node_id = \Drupal::routeMatch()->getRawParameter('node');
        $node = $this->entityTypeManager->getStorage('node')->load($node_id);
      }
    }
    else {
      $node = $this->entityTypeManager->getStorage('node')->load($node_id);
    }

    if ($node->hasField('field_subtheme') && !$node->get('field_subtheme')->isEmpty()) {
      $this->termId = (int) $node->get('field_subtheme')->getString();
    }

    // Add the current node as a cache tag for when theme/subtheme is changed.
    $this->cacheTags[] = 'node:' . $node->id();

    $this->getContent();
    return $this;
  }

  /**
   * Returns the current theme content as an array.
   *
   * @return array
   *   An array of theme content.
   */
  public function asArray(): array {
    return $this->content;
  }

  /**
   * Returns the current theme content as an render array.
   *
   * @return array
   *   A Drupal render array of theme content.
   */
  public function asRenderArray(): array {
    $this->cacheTags[] = 'taxonomy_term_list:' . $this->termId;
    $this->cacheTags[] = 'taxonomy_term:' . $this->termId;

    $items = [];
    foreach ($this->content as $item) {
      $items[] = [
        '#type' => 'link',
        '#title' => $item['title'],
        '#url' => $item['url'],
      ];

      if ($item['entity'] instanceof TermInterface) {
        $this->cacheTags[] = 'taxonomy_term:' . $item['entity']->id();
      }
      else {
        $this->cacheTags[] = 'node:' . $item['entity']->id();
      }

    }

    return [
      'related_content' => [
        '#theme' => 'item_list',
        '#items' => $items,
        '#cache' => [
          'tags' => $this->cacheTags,
          'contexts' => ['url.path'],
        ],
      ],
    ];
  }

  /**
   * Removes the currently viewed term from the content results.
   *
   * Looks at the current page request for a taxonomy parameter and removes the
   * term from the content list.
   *
   * @return $this
   */
  public function excludingCurrentTheme() {
    $route_name = $this->routeMatch->getRouteName();

    if ($route_name === 'entity.taxonomy_term.canonical') {
      $term_id = $this->routeMatch->getRawParameter('taxonomy_term');

      foreach ($this->content as $key => $item) {
        if ($item['entity'] instanceof TermInterface && $item['entity']->id() == $term_id) {
          unset($this->content[$key]);
        }
      }

    }
    return $this;
  }

  /**
   * Fetches and sorts content.
   */
  protected function getContent() {
    if ($this->returnContentTypes === self::CONTENT_THEMES) {
      $this->getThemeSubThemes();
    }
    elseif ($this->returnContentTypes === self::CONTENT_NODES) {
      $this->getThemeNodes();
    }
    else {
      $this->getThemeSubThemes();
      $this->getThemeNodes();
    }

    // Sort the content list by title alphabetically.
    array_multisort(array_column($this->content, 'title_sort'), SORT_ASC, $this->content);
  }

  /**
   * Fetches node content for the term ids.
   */
  protected function getThemeNodes() {

    $related_content_view = $this->entityTypeManager->getStorage('view')->load('related_content_manager__content');
    $content_view = $related_content_view->getExecutable();

    // Fetch nodes by parent term.
    $content_view->setDisplay('by_parent_term');
    $content_view->setArguments([$this->termId]);
    $content_view->execute();

    $parent_rows = $content_view->result;
    $content_view->destroy();

    // Fetch nodes by supplementary term.
    $content_view->setDisplay('by_supplementary_term');
    $content_view->setArguments([$this->termId]);
    $content_view->execute();

    $supplementary_rows = $content_view->result;

    $rows = array_merge($parent_rows, $supplementary_rows);

    foreach ($rows as $row) {

      /** @var \Drupal\node\NodeInterface $entity */
      $entity = $row->_entity;

      // If we are dealing with a book entry and it's lower than the first page,
      // don't add to the list of articles for the taxonomy term.
      if (!empty($entity->book) && ($entity->book['depth'] ?? 0) > 1) {
        continue;
      }

      // External link nodes' titles should be replaced with the link value
      // they contain.

      if ($entity->bundle() === 'external_link') {
        /** @var \Drupal\link\Plugin\Field\FieldType\LinkItem $field_link */
        $field_link = $entity->get('field_link')->first();

        $link_values = $field_link->getValue();
        $title = $link_values['title'];
        $url = Url::fromUri($link_values['uri']);
      }
      else {
        $title = $entity->getTitle();
        $url = Url::fromRoute('entity.node.canonical', ['node' => $entity->id()]);
      }

      $this->content[$entity->id()] = [
        'entity' => $entity,
        'title' => $title,
        'title_sort' => strtolower($title),
        'url' => $url,
      ];
    }

  }

  /**
   * Fetches child themes for the term id.
   */
  protected function getThemeSubThemes() {
    $campaign_terms = $this->getTermsWithCampaignPages();

    $related_content_view = $this->entityTypeManager->getStorage('view')->load('related_content_manager__terms');
    $subtopics_view = $related_content_view->getExecutable();

    $subtopics_view->setDisplay('by_parent_term');
    $subtopics_view->setArguments([$this->termId]);
    $subtopics_view->execute();

    $parent_rows = $subtopics_view->result;
    $subtopics_view->destroy();

    $subtopics_view->setDisplay('by_supplementary_term');
    $subtopics_view->setArguments([$this->termId]);
    $subtopics_view->execute();

    $supplementary_rows = $subtopics_view->result;

    $rows = array_merge($parent_rows, $supplementary_rows);

    foreach ($rows as $row) {
      /** @var \Drupal\taxonomy\TermInterface $entity */
      $entity = $row->_entity;
      $tid = $entity->id();

      // Lookup the list of landing/campaign pages for matches against the
      // current row tid. If we get a match, insert a entry for the landing page
      // node and skip adding the term entry.
      if (array_key_exists($tid, $campaign_terms)) {
        // This will be a link to a campaign (landing page).
        $this->content[$campaign_terms[$tid]->id()] = [
          'entity' => $campaign_terms[$tid],
          'title' => $campaign_terms[$tid]->getTitle(),
          'title_sort' => strtolower($campaign_terms[$tid]->getTitle()),
          'url' => Url::fromRoute('entity.node.canonical', ['node' => $campaign_terms[$tid]->id()]),
        ];
        continue;
      }

      $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($tid);
      $flags = $this->flagService->getAllEntityFlaggings($term);

      if ($flags) {
        foreach ($flags as $flag) {
          // If we have a term flagged as 'Hide Theme' don't add an entry.
          if ($flag->getFlagId() === 'hide_theme') {
            continue 2;
          }
        }
      }

      $this->content[$term->id()] = [
        'entity' => $term,
        'title' => $term->getName(),
        'title_sort' => strtolower($term->getName()),
        'url' => Url::fromRoute('entity.taxonomy_term.canonical', ['taxonomy_term' => $term->id()]),
      ];
    }
  }

  /**
   * Returns an array of term id's with a campaign page.
   *
   * @return array
   *   Term ID indexed array of node objects.
   */
  protected function getTermsWithCampaignPages() {
    // Array of terms with campaign pages.
    $terms = [];

    // Fetch every published campaign page.
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'landing_page')
      ->condition('status', 1);
    $nids = $query->execute();

    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

    // Construct the terms array assigning the campaign node to each
    // overridden tid.
    foreach ($nodes as $node) {
      if (isset($node->get('field_subtheme')->target_id)) {
        $terms[$node->get('field_subtheme')->getString()] = $node;
      }
    }

    return $terms;
  }

}

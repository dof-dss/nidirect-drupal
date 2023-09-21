<?php

namespace Drupal\nidirect_news\Controller;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\nidirect_common\ViewsMetatagManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller for news entity listings.
 */
class NewsListingController extends ControllerBase {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;
  /**
   * Drupal\Core\Block\BlockManagerInterface definition.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $pluginManagerBlock;
  /**
   * Symfony\Component\HttpFoundation\RequestStack definition.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * ViewMetatagManager service.
   *
   * @var \Drupal\nidirect_common\ViewsMetatagManager
   */
  protected $viewsMetaTagManager;

  /**
   * Constructs a new NewsListingController object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager,
                              BlockManagerInterface $plugin_manager_block,
                              RequestStack $request_stack,
                              ViewsMetatagManager $views_metatag_manager) {

    $this->entityTypeManager = $entity_type_manager;
    $this->pluginManagerBlock = $plugin_manager_block;
    $this->requestStack = $request_stack;
    $this->viewsMetaTagManager = $views_metatag_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.block'),
      $container->get('request_stack'),
      $container->get('nidirect_common.views_metatags_manager')
    );
  }

  /**
   * Default page listing.
   *
   * Show:
   * - Latest news: teaser view per item
   * - Older news: title + date per item, with full pager.
   *
   * @return array
   *   Render array for the page for Drupal to convert to HTML.
   */
  public function default() {
    $content = [];

    // Empty page parameter means we're on the landing page for news.
    if (empty($this->requestStack->getCurrentRequest()->query->get('page'))) {
      $latest_news_nids = [];

      // Load/execute the view to extract the node ids; we add these as
      // exclusions to the 'older news items' contextual args to avoid
      // duplication between the two blocks and retain the 'sticky'
      // flag ability to pin important items to the list of top four items.
      $display_id = 'latest_news';
      $view = $this->getNewsView($display_id);

      if (!empty($view->result)) {
        // Latest news.
        $content['latest_news'] = $view->buildRenderable($display_id);

        // Extract row node ids for exclusion in 'older news' embed display below.
        foreach ($view->result as $index => $row) {
          if (!empty($row->nid)) {
            $latest_news_nids[] = $row->nid;
          }
        }
      }

      // View title: see views_embed_view() which the render array relies on for
      // details of why this is missing.
      $content['older_news_title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#attributes' => [
          'class' => 'hr-above',
        ],
        '#value' => t('Other news items'),
      ];
    }

    // Older news.
    $content['older_news'] = [
      '#type' => 'view',
      '#name' => 'news',
      '#display_id' => 'older_news',
    ];

    if (!empty($latest_news_nids)) {
      $content['older_news']['#arguments'] = [implode(',', $latest_news_nids)];
    }

    // Append any configured metatags for the page header.
    // We're borrowing the 'latest_news' display as a vehicle
    // for making these tags configurable, rather than bake them
    // into the source code here.
    $tags = $this->viewsMetaTagManager->getMetatagsForView('news', 'latest_news');
    $content = $this->viewsMetaTagManager->addTagsToPageRender($content, $tags);

    return $content;
  }

  /**
   * @param string $display_id
   *   Machine ID of the views display required.
   * @return \Drupal\views\ViewExecutable
   *   The news view object.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getNewsView($display_id = 'latest_news') {
    $view = $this->entityTypeManager()->getStorage('view')->load('news')->getExecutable();
    $view->setDisplay($display_id);
    $view->initHandlers();
    $view->preExecute();
    $view->execute();

    return $view;
  }

  /**
   * Returns node ids of content we expect to appear in the 'featured' or 'latest news'
   * sections on the news landing page (/news).
   * @return array
   *   Array of node ids.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getFeaturedNewsIds() {
    $view = $this->getNewsView('latest_news');

    $nids = [];

    foreach ($view->result as $row) {
      if (!empty($row->nid)) {
        $nids[] = $row->nid;
      }
    }

    return $nids;
  }

}

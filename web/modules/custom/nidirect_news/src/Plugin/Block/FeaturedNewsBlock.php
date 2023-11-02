<?php

namespace Drupal\nidirect_news\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\nidirect_news\Controller\NewsListingController;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Block for presenting featured news; shared with news embed display
 * on the main news landing page. See NewsListingController.php::default().
 *
 * @Block(
 *  id = "featured_news_block",
 *  admin_label = @Translation("Featured news"),
 * )
 */
class FeaturedNewsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The news controller service.
   *
   * @var \Drupal\nidirect_news\Controller\NewsListingController
   */
  protected $newsService;

  /**
   * ContactAzBlock constructor.
   *
   * @param array $configuration
   *   Site configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\nidirect_news\Controller\NewsListingController $news_service
   *   News controller service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, NewsListingController $news_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->newsService = $news_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('nidirect_news.news')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $view = $this->newsService->getNewsView();
    $content['featured_news'] = $view->buildRenderable('latest_news_block');

    return $content;
  }

}

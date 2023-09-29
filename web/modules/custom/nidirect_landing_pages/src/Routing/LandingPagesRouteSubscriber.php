<?php

namespace Drupal\nidirect_landing_pages\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Class LandingPagesRouteSubscriber.
 *
 * Listens to the dynamic route events.
 */
class LandingPagesRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('layout_builder.choose_section')) {
      $route->setDefaults([
        '_controller' => '\Drupal\nidirect_landing_pages\Controller\LandingPagesChooseSectionController::build',
      ]);
    }

    if ($route = $collection->get('layout_builder.choose_inline_block')) {
      $route->setDefaults([
        '_controller' => '\Drupal\nidirect_landing_pages\Controller\LandingPagesChooseBlockController::inlineBlockList',
      ]);
    }
  }

}

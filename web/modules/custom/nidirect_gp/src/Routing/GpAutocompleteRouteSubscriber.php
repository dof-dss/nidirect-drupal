<?php

namespace Drupal\nidirect_gp\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Route subscriber for handing entity autocomplete requests.
 */
class GpAutocompleteRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('system.entity_autocomplete')) {
      $route->setDefault('_controller', '\Drupal\nidirect_gp\Controller\GpAutocompleteController::handleAutocomplete');
    }
  }

}

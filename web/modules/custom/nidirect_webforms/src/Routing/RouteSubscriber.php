<?php

namespace Drupal\nidirect_webforms\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    /*
     * Deny access to unprivileged users for the webform entity's
     * canonical route. See D8NID-1657 for further details.
     */
    if ($route = $collection->get('entity.webform.canonical')) {
      $route->setRequirement('_permission', 'administer webform');
    }
  }

}

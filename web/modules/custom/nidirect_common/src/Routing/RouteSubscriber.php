<?php

namespace Drupal\nidirect_common\Routing;

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
     * Adjust the form title to make it clear we're only offering basic media
     * attributes here and for the full edit options, the editor needs to open
     * the full canonical entity form using the button provided (see
     * nidirect_common/inc/form_alter.inc for details); based on a patch
     * found at https://www.drupal.org/project/drupal/issues/3168868).
     *
     * See https://www.drupal.org/project/drupal/issues/3132324 for background
     * as to the UX issues around presenting the full entity form in the modal,
     * plus ongoing discussion in core. See '\Drupal\media\Form\EditorMediaDialog'
     * for the implementation of that form and its AJAX handling, which is fairly
     * tricky to modify and adapt to present and handle extra fields.
     */
    if ($route = $collection->get('editor.media_dialog')) {
      $route->setDefault('_title', 'Override media properties');
    }
  }

}

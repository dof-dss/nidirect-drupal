<?php

namespace Drupal\nidirect_temporary\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Temporary controller for Government departments contacts.
 */
class GovDeptController extends ControllerBase {

  /**
   * Disp.
   *
   * @return string
   *   Return empty string.
   */
  public function disp() {
    // Once the site is in production, this controller may be replaced by
    // simply creating a node of type 'Page' with a title of 'Northern
    // Ireland government departments' and a path of
    // '/contacts/government-departments-in-northern-ireland'.
    return [
      '#type' => 'markup',
      '#markup' => '',
    ];
  }

}

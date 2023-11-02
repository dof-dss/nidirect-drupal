<?php

namespace Drupal\nidirect_homepage\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for handling the site root path.
 *
 * This seemingly inconspicuous class responds to the default site route
 * callback and is intended to replace the Drupal default of '/node',
 * which is handled by a view and shows any content promoted to the front
 * page. This is bad news for two reasons:
 *
 * 1. The homepage is mostly comprised of blocks rendered into page regions.
 * 2. If a node is accidentally promoted to the front page using the
 *    'frontpage' view, then it will begin to inject rendered nodes in some
 *    view mode alongside the defined blocks, and disrupt the display of
 *    the homepage.
 *
 * So by keeping our controller here, responding with an empty render array,
 * we protect ourselves from this rather large volume of proverbial egg
 * destined for the face, and ensure that our blocks can render in the regions
 * without being bothered.
 */
class HomepageController extends ControllerBase {

  /**
   * Default callback.
   *
   * Site themes content is provided by a views block:
   * views_block__site_themes_site_themes_home_page
   * which is only shows on <front>.
   *
   * Featured content follows the same pattern (block id: featuredcontent),
   * but is displayed in the page bottom region on the <front> route.
   *
   * @return array
   *   Return a render array.
   */
  public function default() {
    return [];
  }

}

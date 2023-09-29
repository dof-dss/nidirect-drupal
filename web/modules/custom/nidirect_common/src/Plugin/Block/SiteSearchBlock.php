<?php

namespace Drupal\nidirect_common\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Block for containing Site Search View.
 *
 * This block acts as a wrapper around a views embed display.
 * A block display alone requires AJAX on exposed filters which
 * means the search autocomplete will reload the same page if
 * no item is selected from the autocomplete list. What we want
 * it to do is respect the original action attribute on the form
 * element and ensure if no autocomplete terms are selected and
 * the form is submitted (eg: with the enter key) then the browser
 * follows the path defined in the action attribute.
 *
 * @Block(
 *  id = "site_search_block",
 *  admin_label = @Translation("Site search block"),
 * )
 */
class SiteSearchBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build['site_search_block']['site_search'] = [
      '#type' => 'view',
      '#name' => 'search',
      '#display_id' => 'site_search',
    ];

    return $build;
  }

}

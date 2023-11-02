<?php

namespace Drupal\nicsdru_nidirect_theme;

use Drupal\block\Entity\Block;

/**
 * Theme helper functions.
 *
 * @package Drupal\nicsdru_nidirect_theme
 */
class Helpers {

  /**
   * Loads and returns block content.
   *
   * @param string $block_id
   *   Machine name of the block content to fetch.
   *
   * @return array|null
   *   Block render array or null if block not found.
   */
  public static function blockContent($block_id) {
    $block = Block::load($block_id);
    if ($block !== NULL) {
      return \Drupal::entityTypeManager()->getViewBuilder('block')->view($block);
    }
    return NULL;
  }

}

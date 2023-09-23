<?php

/**
 * @file
 * Contains gp.page.inc.
 *
 * Page callback for GP entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for GP templates.
 *
 * Default template: gp.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_gp(array &$variables) {
  // Fetch Gp Entity Object.
  $gp = $variables['elements']['#gp'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}

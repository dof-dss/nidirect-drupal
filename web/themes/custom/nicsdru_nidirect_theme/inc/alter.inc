<?php

/**
 * @file
 * Theme hook_alter functions.
 */

use Drupal\Core\Form\FormStateInterface;

/**
 * Implements hook_page_attachments_alter().
 */
function nicsdru_nidirect_theme_page_attachments_alter(array &$page) {

  // Attach layout builder styles for authenticated users.
  $userCurrent = \Drupal::currentUser();

  if ($userCurrent->isAuthenticated()) {
    $page['#attached']['library'][] = 'nicsdru_nidirect_theme/authenticated-styles';
  }

  // Attach JS for layout builder tweaks.
  if (\Drupal::routeMatch()->getRouteName() === 'layout_builder.overrides.node.view') {
    $page['#attached']['library'][] = 'nicsdru_nidirect_theme/nidirect-ui';
  }
}

/**
 * Implements hook_form_BASE_FORM_ID_alter().
 */
function nicsdru_nidirect_theme_form_node_preview_form_select_alter(&$form, FormStateInterface $form_state, $form_id) {
  // Additional styles to the node preview form.
  unset($form['view_mode']);
  $form['backlink']['#options']['attributes']['class'] += [
    'btn-standard',
    'call-to-action',
  ];
}

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
function nicsdru_nidirect_theme_theme_suggestions_field_alter(array &$suggestions, array $variables) {
  // Force related info/More useful links fields to use the
  // related_info template.
  if ($variables['element']['#field_name'] === 'field_related_info') {
    $suggestions = ['field__field_related_info'];
  }
}

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
function nicsdru_nidirect_theme_theme_suggestions_item_list_alter(array &$suggestions, array $variables) {
  // If an item list has the 'nav-menu' class, render using
  // the templates/navigation/item-list--nav-menu.html.twig template.
  if (isset($variables['attributes']['class']) && is_array($variables['attributes']['class'])) {
    if (in_array('nav-menu', $variables['attributes']['class'])) {
      $suggestions = ['item_list__nav_menu'];
    }
  }
}

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
function nicsdru_nidirect_theme_theme_suggestions_block_alter(array &$suggestions, array $variables) {
  $plugin_id = $variables['elements']['#plugin_id'];

  // Update Layout Builder Standard and Wide inline card blocks to use
  // templates/block/block--inline-block--card.html.twig.
  if ($plugin_id === 'inline_block:card_wide' || $plugin_id === 'inline_block:card_standard') {
    $suggestions[] = 'block__inline_block__card';
  }
}

/**
 * Implements hook_form_FORM_ID_alter().
 */
function nicsdru_nidirect_theme_form_node_landing_page_layout_builder_form_alter(&$form, FormStateInterface $form_state, $form_id) {
  // Hide revision information section.
  $form['revision_information']['#access'] = FALSE;
}

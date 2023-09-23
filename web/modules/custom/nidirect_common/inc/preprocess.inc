<?php

/**
 * @file
 * Contains hook_preprocess functions for nidirect_common.module.
 */

use Drupal\node\Entity\Node;

/**
 * Implements hook_preprocess_views_view().
 */
function nidirect_common_preprocess_views_view(&$variables) {
  if ($variables['view']->id() === 'media_entity_browser') {
    $variables['view_array']['#attached']['library'][] = 'nidirect_common/media_entity_browser';
  }
}

/**
 * Implements hook_preprocess_page().
 */
function nidirect_common_preprocess_page(&$variables) {
  if (\Drupal::service('router.admin_context')->isAdminRoute()) {
    return;
  }

  // Similar to how core adds the node object to $variables, we will add
  // the term if we are on a taxonomy page.
  if (\Drupal::routeMatch()->getRouteName() == 'entity.taxonomy_term.canonical') {
    $tid = \Drupal::routeMatch()->getRawParameter('taxonomy_term');
    $variables['term'] = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tid);
  }

  // Apply link manager library to all non-admin pages.
  $variables['#attached']['library'][] = 'nidirect_common/link_manager';

  // Sometimes $variables['node'] is not a node object, but just contains the
  // nid as a string, so we'll avoid errors and allow for that here.
  if (isset($variables['node']) && is_string($variables['node'])) {
    $variables['node'] = Node::load($variables['node']);
  }

  // Assign our banner image entity, either a node or a taxonomy term.
  if (isset($variables['node'])) {
    $entity = $variables['node'];
  }
  elseif (isset($variables['term'])) {
    $entity = $variables['term'];
  }

  // Fetch and display the node or theme banner image and footer text.
  if (!empty($entity)) {
    $banner_image = nidirect_common_retrieve_hierarchical_field($entity, 'field_banner_image');

    if (!empty($banner_image)) {
      $variables['page']['top_banner']['banner_display'] = $banner_image->view([
        'label' => 'hidden',
        'type' => 'entity_reference_entity_view',
        'settings' => [
          'view_mode' => 'banner_thin',
          'link' => FALSE,
        ],
      ]);
    }
  }
}

/**
 * Implements hook_preprocess_status_messages().
 */
function nidirect_common_preprocess_status_messages(&$variables) {
  // Add additional explanation to to the oEmbed resource not found message.
  if (isset($variables['message_list']['error'])) {
    $error_messages = $variables['message_list']['error'];

    foreach ($error_messages as $index => $message) {
      if ($message == 'Could not retrieve the oEmbed resource.') {
        $message .= t("This may be due to media being inaccessible or set to private.");
        $variables['message_list']['error'][$index] = $message;
      }
    }
  }
}

/**
 * Implements hook_preprocess_inline_entity_form_entity_table().
 */
function nidirect_common_preprocess_inline_entity_form_entity_table(&$variables) {
  // Replace the label for the 'delete node from system' checkbox.
  if (!empty($variables['table'])) {
    foreach ($variables['table'] as $key => &$value) {
      if (is_numeric($key) && !empty($value['0']['delete']['#title'])) {
        $value['0']['delete']['#title'] = 'Delete alternative title from the system';
      }
    }
  }
}

/**
 * Implements hook_preprocess_form_element().
 */
function nidirect_common_preprocess_form_element(&$variables) {
  $route = \Drupal::routeMatch()->getRouteName();

  // Force the admin Content Theme/Subtheme select element onto a new line.
  if ($route === 'system.admin_content'
    && isset($variables['element']['#id'])
    && $variables['element']['#id'] === 'edit-term-node-tid-depth') {
    $variables['attributes']['style'] = "float: none; clear: both";
  }
}

/**
 * Implements hook_preprocess_taxonomy_term().
 */
function nidirect_common_preprocess_taxonomy_term(array &$variables) {
  if ($variables['term']->bundle() != 'site_themes') {
    // We are only interested in the 'site themes' vocab.
    return;
  }

  // Return as an 'item_list' themed render array of content for this term
  // with cache tags set appropriately.
  $content_manager = \Drupal::service('nidirect_related_content.manager');
  $content = $content_manager
    ->getSubThemesAndNodes()
    ->forTheme()
    ->excludingCurrentTheme()
    ->asRenderArray();

  $variables['information_services_output'] = $content;
}

/**
 * Implements hook_preprocess_webform_actions().
 */
function nidirect_common_preprocess_webform_actions(array &$variables) {
  $route_match = \Drupal::routeMatch();

  if ($route_match->getRouteName() == 'entity.node.canonical') {
    $node = $route_match->getParameter('node');

    // If the node has a Webform attached, extract the id for comparison.
    if ($node->hasField('webform') && !$node->get('webform')->isEmpty()) {
      $webform_id = $node->get('webform')->first()->get('target_id')->getValue();
      // Remove the wizard previous page link for taxi driver theory tests only.
      if (substr($webform_id, 0, 27) === 'taxi_driver_theory_practice') {
        unset($variables['wizard_prev']);
        unset($variables['element']['wizard_prev']);
      }
    }
  }
}

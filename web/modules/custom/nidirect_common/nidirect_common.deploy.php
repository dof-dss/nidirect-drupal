<?php

/**
 * @file
 * Deployment hooks for NIDirect common functionality.
 */

/**
 * Removes schema records left behind by extensions no longer in the codebase.
 */
function nidirect_common_deploy_11001(): void {
  $removed_extensions = [
    'block_content_permissions',
    'easy_install',
    'file_delete_ui',
    'kint',
    'migrate_nidirect_node_webform',
    'nidirect_media',
    'twig_extensions',
  ];

  \Drupal::service('keyvalue')
    ->get('system.schema')
    ->deleteMultiple($removed_extensions);
}

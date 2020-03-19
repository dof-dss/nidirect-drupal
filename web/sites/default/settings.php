<?php

// Default Drupal 8 settings.
//
// These are already explained with detailed comments in Drupal's
// default.settings.php file.
//
// See https://api.drupal.org/api/drupal/sites!default!default.settings.php/8
$databases = [];
$config_directories = [];
$settings['update_free_access'] = FALSE;
$settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.yml';
$settings['file_scan_ignore_directories'] = [
  'node_modules',
  'bower_components',
];

// Site hash salt.
$settings['hash_salt'] = getenv('HASH_SALT');

// Configuration sync base/default directory.
$settings['config_sync_directory'] = getenv('CONFIG_SYNC_DIRECTORY');

// Temp directory.
$settings["file_temp_path"] = getenv('FILE_TEMP_PATH') ?? '/tmp';

// Set config split environment; environment specific values is set near the end of this file.
$config['config_split.config_split.local']['status'] = FALSE;
$config['config_split.config_split.development']['status'] = FALSE;
$config['config_split.config_split.production']['status'] = FALSE;

// Config readonly settings.
$settings['config_readonly'] = getenv('CONFIG_READONLY');

// Configuration that is allowed to be changed in readonly environments.
$settings['config_readonly_whitelist_patterns'] = [
  'system.site',
];

// Environment indicator config.
$settings['simple_environment_indicator'] = sprintf('%s %s', getenv('SIMPLEI_ENV_COLOUR'), getenv('SIMPLEI_ENV_NAME'));

// Geocoder API key.
$config['geolocation.settings']['google_map_api_key'] = getenv('GOOGLE_MAP_API_KEY');

// If we're running on platform.sh, check for and load relevant settings.
if (!empty(getenv('PLATFORM_BRANCH'))) {
  if (file_exists($app_root . '/' . $site_path . '/settings.platformsh.php')) {
    include $app_root . '/' . $site_path . '/settings.platformsh.php';
  }

  // Environment specific settings and services.
  switch (getenv('PLATFORM_BRANCH')) {
    case 'master':
      // De-facto production settings.
      $config['config_split.config_split.production']['status'] = TRUE;

    default:
      // Default to use development settings/services for general platform.sh environments.
      $config['config_split.config_split.development']['status'] = TRUE;
      $settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.development.yml';
      include $app_root . '/' . $site_path . '/settings.development.php';
  }
}

// Local settings. These come last so that they can override anything.
if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}

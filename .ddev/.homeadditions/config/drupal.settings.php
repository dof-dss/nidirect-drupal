<?php

// @codingStandardsIgnoreFile
$local_services_config = $app_root . '/sites/local.development.services.yml';
if (file_exists($local_services_config)) {
  $settings['container_yamls'][] = $local_services_config;
}

// Add migration source database details, if needed.
// NB: uses the same db container to store the source db.
// This can be useful for cross-db queries.
$databases['default']['drupal7db'] = [
  'database' => getenv('MIGRATE_SOURCE_DB_NAME'),
  'username' => getenv('MIGRATE_SOURCE_DB_USER'),
  'password' => getenv('MIGRATE_SOURCE_DB_PASS'),
  'host' => getenv('MIGRATE_SOURCE_DB_HOST'),
  'port' => getenv('MIGRATE_SOURCE_DB_PORT'),
  'driver' => getenv('MIGRATE_SOURCE_DB_DRIVER'),
];

// Prevent SqlBase from moaning.
$databases['migrate']['default'] = $databases['default']['drupal7db'];

// Set config split environment.
$config['config_split.config_split.local']['status'] = TRUE;
$config['config_split.config_split.development']['status'] = FALSE;
$config['config_split.config_split.production']['status'] = FALSE;

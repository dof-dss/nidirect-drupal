<?php

// @codingStandardsIgnoreFile
$local_services_config = $app_root . '/sites/local.development.services.yml';
if (file_exists($local_services_config)) {
  $settings['container_yamls'][] = $local_services_config;
}

// Set config split environment.
$config['config_split.config_split.local']['status'] = TRUE;
$config['config_split.config_split.development']['status'] = FALSE;
$config['config_split.config_split.production']['status'] = FALSE;

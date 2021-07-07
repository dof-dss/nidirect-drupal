<?php

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

$autoloader = require_once 'autoload.php';

$kernel = DrupalKernel::createFromRequest(Request::createFromGlobals(), $autoloader, 'prod');
$kernel->boot();

$container = $kernel->getContainer();
$database = $container->get('database');

$errors = [];

/**
 * Query the users table to verify that the database connection is active.
 */
$result = $database->query("SELECT uid FROM {users} WHERE uid = 1")->fetch();

if (empty($result->uid)) {
  $errors[] = 'Database query failed';
}

/**
 * Check that the site is not running in maintenance mode.
 */
$maintenance_mode = $container->get('state')->get('system.maintenance_mode');

if ($maintenance_mode) {
  $errors[] = 'Site offline: maintenance mode';
}

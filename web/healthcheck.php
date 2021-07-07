<?php

use Drupal\Core\DrupalKernel;
use Drupal\Core\StreamWrapper\PublicStream;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$autoloader = require_once 'autoload.php';

$kernel = DrupalKernel::createFromRequest(Request::createFromGlobals(), $autoloader, 'prod');
$kernel->boot();

$container = $kernel->getContainer();
$errors = [];

/**
 * Query the users table to verify that the database connection is active.
 */
$database = $container->get('database');
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

/**
 * Check that the files directory is operating properly.
 */
$public_fs_path = PublicStream::basePath();

if ($public_fs_path && $temp_file = tempnam($public_fs_path, 'status_check_')) {
    if (!unlink($temp_file)) {
      $errors[] = 'Could not delete newly created file in the files directory.';
    }
} else {
    $errors[] = 'Could not create a file in the files directory.';
}

$response = new Response();
$response->setExpires(new DateTime('01/15/2001'));
$response->setLastModified(new DateTime());

// Format the response output.
if (count($errors) > 0) {
  $output = 'NOK' . ' 500' . '<br />' . implode("<br />\n", $errors);
  $response->setContent($output);
  $response->setStatusCode(500);
} else {
  $response->setContent('OK' . ' 200');
}

$response->send();

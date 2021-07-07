<?php

use Drupal\Core\DrupalKernel;
use Drupal\Core\StreamWrapper\PublicStream;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$autoloader = require_once 'autoload.php';

$errors = [];
$request = Request::createFromGlobals();

$kernel = DrupalKernel::createFromRequest($request, $autoloader, 'prod');
try {
  $kernel->boot();
  $kernel->preHandle($request);
} catch (Exception $e) {
  // Remove database username from exception message.
  $message = preg_replace('/\'(.*)\'(@\'.*\')/m', '\'*****\'$2', $e->getMessage());
  $errors[] = 'Unable to boot the kernel: ' . $message;
}

// If the kernel is healthy, process with the checks.
if (empty($errors)) {
  $container = $kernel->getContainer();

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
}

// Create and return a new Response.
$response = new Response();
$response->setExpires(new DateTime('01/15/2001'));
$response->setLastModified(new DateTime());

// Manually set headers for cache control.
$response->headers->set('Cache-Control', ['no-store', 'must-revalidate', 'stale-while-revalidate=0', 'stale-if-error=0']);
$response->headers->set('Surrogate-Control', 'max-age=30');

// Format the response output.
if (count($errors) > 0) {
  $output = 'NOK' . ' 500' . '<br />' . implode("<br />\n", $errors);
  $response->setContent($output);
  $response->setStatusCode(500);
} else {
  $response->setContent('OK' . ' 200');
}

$response->send();

$kernel->terminate($request, $response);
$kernel->shutdown();

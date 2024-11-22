<?php

namespace Drupal\nidirect_prisons\Authentication\Provider;

use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides authentication for requests based on IP address.
 */
class IpAddressAuth implements AuthenticationProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request) {
    // Restrict to endpoints under /api/prisoner-payments/*
    $path = $request->getPathInfo();
    return str_starts_with($path, '/api/prisoner-payments');
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request) {
    // Get the client's IP address.
    $client_ip = $request->getClientIp();

    // Get the allowed IP addresses from environment variables.
    $allowed_ip_addresses = explode(',', getenv('PRISONS_API_PERMITTED_IPS'));
    $allowed_ip_addresses = array_map('trim', $allowed_ip_addresses);

    // Check if the client IP is allowed.
    if (in_array($client_ip, $allowed_ip_addresses)) {
      // IP is allowed; return an anonymous user (or specific user account).
      return \Drupal::entityTypeManager()->getStorage('user')->load(0); // Anonymous user.
    }

    // IP not allowed; return NULL to deny access.
    return NULL;
  }
}

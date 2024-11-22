<?php

namespace Drupal\nidirect_prisons\Authentication\Provider;

use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides authentication for requests using X-Auth-Token.
 */
class XAuthToken implements AuthenticationProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request) {
    // Check if the request contains the X-Auth-Token header.
    return $request->headers->has('X-Auth-Token');
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request) {
    // Extract the token from the header.
    $token = $request->headers->get('X-Auth-Token');

    // Validate the token (you can enhance this with your logic).
    $allowed_tokens = explode(',', getenv('PRISONS_API_PERMITTED_TOKENS'));
    $allowed_tokens = array_map('trim', $allowed_tokens);

    if (!in_array($token, $allowed_tokens)) {
      return NULL; // Invalid token; return NULL to deny access.
    }

    // Token is valid; return an anonymous user (or a specific user account).
    return \Drupal::entityTypeManager()->getStorage('user')->load(0); // Anonymous user.
  }
}

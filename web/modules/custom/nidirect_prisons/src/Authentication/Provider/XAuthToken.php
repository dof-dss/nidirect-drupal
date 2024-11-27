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
    // Extract the token from the header and check it is valid.
    $token = $request->headers->get('X-Auth-Token');
    $allowed_tokens = explode(',', getenv('PRISONS_API_PERMITTED_TOKENS'));
    $allowed_tokens = array_map('trim', $allowed_tokens);

    if (!in_array($token, $allowed_tokens)) {
      return NULL;
    }

    // Token is valid, return an anonymous user.
    return \Drupal::entityTypeManager()->getStorage('user')->load(0);
  }

}

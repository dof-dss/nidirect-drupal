<?php

namespace Drupal\nidirect_prisons\Authentication\Provider;

use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides token and IP authentication for
 * Prisoner Payments REST resources.
 */
class TokenAndIpAddressAuth implements AuthenticationProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request) {
    // Do not apply to requests missing the X-Auth-Token header.
    if (!$request->headers->has('X-Auth-Token')) {
      return FALSE;
    }

    // Apply to endpoints under:
    //   /api/{version}/prisoner-payments and
    //   /api/{version}/prisoner-visits.
    $path = $request->getPathInfo();
    return preg_match('#^/api/v\d+/prisoner-payments#', $path) || preg_match('#^/api/v\d+/prison-visits#', $path);
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request) {

    // Allowed tokens and IP addresses.
    $allowed_tokens = array_map('trim', explode(',', getenv('PRISONS_API_PERMITTED_TOKENS')));
    $allowed_ip_addresses = array_map('trim', explode(',', getenv('PRISONS_API_PERMITTED_IPS')));

    // Authentication fails if either token or IP is not allowed.
    $token = $request->headers->get('X-Auth-Token');
    $client_ip = $request->getClientIp();

    if (!in_array($token, $allowed_tokens)) {
      \Drupal::logger('nidirect_prisons')->debug('Supplied X-Auth-Token not found in PRISONS_API_PERMITTED_TOKENS');
      return NULL;
    }

    if (!in_array($client_ip, $allowed_ip_addresses)) {
      \Drupal::logger('nidirect_prisons')->debug('IP address @client_ip not found in PRISONS_API_PERMITTED_IPS.', ['@client_ip' => $client_ip]);
      return NULL;
    }

    // IP and token are allowed. Return nidirect_prisons_api_user
    // user (has authenticated user role).
    $username = 'nidirect_prisons_api_user';
    $authenticated_user = user_load_by_name($username);

    if ($authenticated_user) {
      return $authenticated_user;
    }

    // There must have been a problem loading nidirect_prisons_api_user.
    \Drupal::logger('nidirect_prisons')->error('Service account with username @username could not be loaded.', ['@username' => $username]);

    // Authentication has failed.
    return NULL;
  }

}

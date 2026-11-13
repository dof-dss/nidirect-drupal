<?php

namespace Drupal\nidirect_prisons\Authentication\Provider;

use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides token and IP authentication for
 * Prisoner Payments REST resources.
 */
class TokenAndIpAddressAuth implements AuthenticationProviderInterface {

  /**
   * Constructs a token and IP address authentication provider.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request) {
    // Do not apply to requests missing the X-Auth-Token header.
    if (!$request->headers->has('X-Auth-Token')) {
      return FALSE;
    }

    // Apply to endpoints under:
    // - /api/{version}/prisoner-payments
    // - /api/{version}/prisoner-visits.
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
      // @phpstan-ignore-next-line.
      \Drupal::logger('nidirect_prisons')->debug('Supplied X-Auth-Token not found in PRISONS_API_PERMITTED_TOKENS');
      return NULL;
    }

    if (!in_array($client_ip, $allowed_ip_addresses)) {
      // @phpstan-ignore-next-line.
      \Drupal::logger('nidirect_prisons')->debug('IP address @client_ip not found in PRISONS_API_PERMITTED_IPS.', ['@client_ip' => $client_ip]);
      return NULL;
    }

    // IP and token are allowed. Return nidirect_prisons_api_user
    // user (has authenticated user role).
    $username = 'nidirect_prisons_api_user';
    $users = $this->entityTypeManager
      ->getStorage('user')
      ->loadByProperties(['name' => $username]);
    $authenticated_user = reset($users);

    if ($authenticated_user instanceof UserInterface) {
      return $authenticated_user;
    }

    // There must have been a problem loading nidirect_prisons_api_user.
    // @phpstan-ignore-next-line.
    \Drupal::logger('nidirect_prisons')->error('Service account with username @username could not be loaded.', ['@username' => $username]);

    // Authentication has failed.
    return NULL;
  }

}

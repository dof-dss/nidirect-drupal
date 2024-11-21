<?php

namespace Drupal\prisons_integration;

use Symfony\Component\HttpFoundation\RequestStack;
use Psr\Log\LoggerInterface;
use Drupal\Core\Cache\CacheBackendInterface;

/**
 * Provides common validation logic for the Prisons Integration API.
 */
class PrisonsIntegrationService {

  protected $request;
  protected $logger;
  protected $cache;

  public function __construct(RequestStack $request_stack, LoggerInterface $logger, CacheBackendInterface $cache) {
    $this->request = $request_stack->getCurrentRequest();
    $this->logger = $logger;
    $this->cache = $cache;
  }

  /**
   * Validates if the API request is from an allowed client.
   */
  public function isValidRequest() {
    // API restricted to specific client IP addresses and the client must send a secret token.
    $client_ip = $this->request->getClientIp();
    $client_token = $this->request->headers->get('X-Auth-Token');

    // Allowed client IPs and tokens (retrieved from environment variables).
    $allowed_ip_addresses = explode(',', getenv('PRISONS_API_PERMITTED_IPS'));
    $allowed_ip_addresses = array_map('trim', $allowed_ip_addresses);

    $allowed_tokens = explode(',', getenv('PRISONS_API_PERMITTED_TOKENS'));
    $allowed_tokens = array_map('trim', $allowed_tokens);

    // Check for missing environment variables and log once.
    if ((!$allowed_ip_addresses || !$allowed_tokens) &&
      empty($this->cache->get('prisons_api_env_missing_error_logged'))) {
      $msg = 'One or more environment variables are missing: PRISONS_API_PERMITTED_IPS, PRISONS_API_PERMITTED_TOKENS';
      $this->logger->warning($msg);
      $this->cache->set('prisons_api_env_missing_error_logged', $msg, CacheBackendInterface::CACHE_PERMANENT);
    }
    elseif ($allowed_ip_addresses && $allowed_tokens) {
      // Environment variables retrieved successfully; delete cached error.
      $this->cache->delete('prisons_api_env_missing_error_logged');
    }

    // Validate IP and token.
    $ip_is_allowed = !empty($client_ip) && in_array($client_ip, $allowed_ip_addresses);
    $token_is_allowed = !empty($client_token) && in_array($client_token, $allowed_tokens);

    // Return TRUE if both IP and token are valid.
    return $ip_is_allowed && $token_is_allowed;
  }
}

<?php

namespace Drupal\nidirect_prisons;

use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactory;

/**
 * Provides common validation logic for the Prisons Integration API.
 */
class PrisonsIntegrationService {

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Logger channel service object.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Constructs a new PrisonsIntegrationService object.
   */
  public function __construct(RequestStack $request_stack, LoggerChannelFactory $logger, CacheBackendInterface $cache) {
    $this->request = $request_stack->getCurrentRequest();
    $this->logger = $logger;
    $this->cache = $cache;
  }

  /**
   * Validates if the API request is from an allowed client.
   *
   * @return bool
   *   TRUE if the request is valid, FALSE otherwise.
   */
  public function isValidRequest() {
    // API restricted to specific client IP addresses and the client must send a secret token.
    $client_ip = $this->request->getClientIp();
    $client_token = $this->request->headers->get('X-Auth-Token');

    // Allowed client IPs and tokens (retrieved from environment variables).
    $allowed_ip_addresses = array_filter(array_map('trim', explode(',', getenv('PRISONS_API_PERMITTED_IPS') ?: '')));
    $allowed_tokens = array_filter(array_map('trim', explode(',', getenv('PRISONS_API_PERMITTED_TOKENS') ?: '')));

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

    // Log unauthorized attempts.
    if (!$ip_is_allowed || !$token_is_allowed) {
      $this->logger->warning('Unauthorized request from IP: @ip with Token: @token', [
        '@ip' => $client_ip,
        '@token' => $client_token,
      ]);
    }

    // Return TRUE if both IP and token are valid.
    return $ip_is_allowed && $token_is_allowed;
  }
  
}

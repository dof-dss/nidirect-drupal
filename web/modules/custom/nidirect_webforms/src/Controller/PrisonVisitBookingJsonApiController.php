<?php

namespace Drupal\nidirect_webforms\Controller;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\UserSession;
use Drupal\file\Entity\File;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Implementing our example JSON api.
 */
class PrisonVisitBookingJsonApiController extends ControllerBase {

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Constructs a PrisonVisitBookingJsonApiController object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   A config factory instance.
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   A http client instance.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A request instance.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   A cache instance.
   */
  public function __construct(ConfigFactoryInterface $configFactory, ClientInterface $httpClient, Request $request, CacheBackendInterface $cache) {
    $this->configFactory = $configFactory;
    $this->httpClient = $httpClient;
    $this->request = $request;
    $this->cache = $cache;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('http_client'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('cache.default'),
    );
  }

  /**
   * Callback for the API.
   */
  public function renderApi() {

    $response = new JsonResponse();

    if ($this->isValidRequest() === FALSE) {
      // Return "401 Unauthorized" response.
      $response->setContent('Unauthorised Request')->setStatusCode(401);
      return $response;
    }
    else {
      // Get cached json data.
      $data = $this->cacheJsonData();

      if (empty($data)) {
        // Return "204 No Content" response.
        $response->setContent('No Content')->setStatusCode(204);
      }
      else {
        // Return data and 200 OK.
        $response->setData($data)->setStatusCode(200);
      }
    }

    return $response;
  }

  /**
   * Helper function to validate request is from allowed IP and
   * request contains X-Auth-Token header with an allowed token.
   */
  private function isValidRequest() {

    // API restricted to specific client IP addresses and the client
    // must send secret token.
    $client_ip = $this->request->getClientIp();
    $client_token = $this->request->headers->get('X-Auth-Token');

    // Allowed client IPs.
    $allowed_ip_addresses = explode(',', getenv('PRISON_VISITS_API_PERMITTED_IPS'));
    $allowed_ip_addresses = array_map('trim', $allowed_ip_addresses);

    // Allowed tokens.
    $allowed_tokens = explode(',', getenv('PRISON_VISITS_API_PERMITTED_TOKENS'));
    $allowed_tokens = array_map('trim', $allowed_tokens);

    if (!$allowed_ip_addresses || !$allowed_tokens) {
      $this->getLogger('prison_visits')->warning('One or more environment variables are missing: PRISON_VISITS_API_PERMITTED_IPS, PRISON_VISITS_API_PERMITTED_TOKENS');
    }

    // Is client ip a private (local) ip?
    $client_ip_is_private = empty(filter_var($client_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE));

    // Is ip and token allowed?
    $ip_is_allowed = !empty($client_ip) && in_array($client_ip, $allowed_ip_addresses) || $client_ip_is_private;
    $token_is_allowed = !empty($client_token) && in_array($client_token, $allowed_tokens);

    // Return a session if the request passes the validation.
    if ($ip_is_allowed && $token_is_allowed) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Helper function to cache posted json data.
   *
   * Data is cached and returned only if it is within size limits can
   * and can be json decoded.
   */
  private function cacheJsonData() {

    $data = [];
    $content = $this->request->getContent();

    // Return no data if posted json content exceeds 100,000 bytes.
    if (strlen($content) > 100000) {
      $this->getLogger('prison_visits')->error('prison_visit_slots_data exceeds 100000 bytes');
      return [];
    }
    elseif (!empty($content)) {

      $now = new \DateTime('now');
      $expire = $now->modify('+1 week');

      // If content can be json decoded, write the decoded data to
      // cache and the json content to a file.
      if ($data = json_decode($content, TRUE)) {

        // Write data to cache.
        $this->cache->set('prison_visit_slots_data', $data, $expire->getTimestamp());
        $this->getLogger('prison_visits')->info('prison_visit_slots_data cache updated.');

        // Write content to file.
        /** @var \Drupal\file\FileRepositoryInterface $fileRepository */
        $fileRepository = \Drupal::service('file.repository');
        $directory = 'private://nidirect_webforms';
        $filepath = $directory . '/prison_visit_slots_data.json';

        // Create file if it doesn't exist.
        $file = $fileRepository->loadByUri($filepath);
        if (empty($file)) {
          /** @var \Drupal\Core\File\FileSystemInterface $file_system */
          $file_system = \Drupal::service('file_system');
          $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
          $file = File::create([
            'filename' => basename($filepath),
            'uri' => $filepath,
            'status' => 1,
            'uid' => 1,
          ]);

          $file->save();

          // Mark the file as used as unused files might be deleted.
          /** @var \Drupal\file\FileUsage\DatabaseFileUsageBackend $file_usage */
          $file_usage = \Drupal::service('file.usage');
          $file_usage->add($file, 'nidirect_webforms', 'webform', 'prison_visit_online_booking');
        }

        // Write content to the file.
        $fileRepository->writeData($content, $filepath, FileSystemInterface::EXISTS_REPLACE);
        $file->save();
        $this->getLogger('prison_visits')->info('prison_visit_slots_data saved to ' . $filepath);
      }
      else {
        $this->getLogger('prison_visits')->error('prison_visit_slots_data update failure.');
      }
    }

    return $data;
  }

}

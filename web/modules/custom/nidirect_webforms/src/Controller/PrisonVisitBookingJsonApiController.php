<?php

namespace Drupal\nidirect_webforms\Controller;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

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

    $data = $this->cacheJsonData();

    $response = new JsonResponse();

    if (!empty($data)) {
      $response->setData($data)->setStatusCode(200);
    }
    else {
      $response->setStatusCode(204);
    }

    return $response;
  }

  /**
   * A helper function to get posted json data.
   */
  public function cacheJsonData() {
    $data = [];
    $content = $this->request->getContent();

    if (!empty($content)) {
      $now = new \DateTime('now');
      $expire = $now->modify('+1 week');

      if ($data = json_decode($content, TRUE)) {
        $this->cache->set('prison_visit_slots_data', $data, $expire->getTimestamp());
        $this->getLogger('prison_visits')->debug('prison_visit_slots_data cache update success.');
      }
      else {
        $this->getLogger('prison_visits')->error('prison_visit_slots_data cache update failure.');
      }
    }

    return $data;
  }

}

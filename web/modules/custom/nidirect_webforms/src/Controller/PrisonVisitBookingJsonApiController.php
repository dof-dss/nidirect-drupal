<?php

namespace Drupal\nidirect_webforms\Controller;

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
   * @var ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * @var ClientInterface
   */
  protected $httpClient;

  /**
   * @var Request
   */
  protected $request;

  /**
   * @var RequestStack
   */
  protected $requestStack;

  /**
   * @param ConfigFactoryInterface $configFactory
   * @param ClientInterface $httpClient
   */

  public function __construct(ConfigFactoryInterface $configFactory, ClientInterface $httpClient, Request $request) {
    $this->configFactory = $configFactory;
    $this->httpClient = $httpClient;
    $this->request = $request;
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
    );
  }

  /**
   * Callback for the API.
   */
  public function renderApi() {

    $data = $this->getPostedJsonData();

    $response = new JsonResponse();

    if (!empty($data)) {
      $response->setData($data)->setStatusCode(200);
    }
    else
    {
      $response->setStatusCode(204);
    }

    return $response;
  }

  /**
   * A helper function to get posted json data.
   */
  public function getPostedJsonData() {
    $data = [];
    $content = $this->request->getContent();

    if (!empty($content)) {
      $now = new \DateTime('now');
      $expire = $now->modify('+1 week');
      $data = json_decode($content, TRUE);
      \Drupal::cache()->set('prison_visit_slots_data', $data, $expire->getTimestamp());
    }

    return $data;
  }

  /**
   * Get external data using http client.
   */
  private function fetchExternalData($uri) {
    $method = 'GET';
    try {
      $response = $this->httpClient->request($method, $uri, ['connect_timeout' => 6, 'timeout' => 10]);
      $code = $response->getStatusCode();
      if ($code == 200) {
        return $response->getBody()->getContents();
      }
    }
    catch(RequestException $e) {
      watchdog_exception('prison_visits', $e);
    }
  }

}

<?php

namespace Drupal\nidirect_school_closures\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\nidirect_school_closures\SchoolClosure;
use Drupal\nidirect_school_closures\SchoolClosureReasonMapper;
use Drupal\nidirect_school_closures\SchoolClosuresServiceInterface;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;

/**
 * Implementation of SchoolClosuresService using the Exceptional Closures API.
 */
class ExceptionalClosuresSchoolClosuresService implements SchoolClosuresServiceInterface {

  /**
   * HTTP request attempt count.
   *
   * @var int
   */
  protected $attempt = 1;

  /**
   * Maximum number of HTTP requests to be made.
   *
   * @var int
   */
  protected $maxAttempts = 3;

  /**
   * Error state.
   *
   * @var bool
   */
  protected $error = FALSE;

  /**
   * URL for the HTTP GET request.
   *
   * @var string
   */
  protected $url = NULL;

  /**
   * Decoded JSON response from the service call.
   *
   * @var array
   */
  protected $responseData = NULL;

  /**
   * Dataset of school closures.
   *
   * @var array
   */
  protected $data = [];

  /**
   * Cache backup.
   *
   * @var object
   */
  protected $cacheBackup = NULL;

  /**
   * Cache duration in minutes for dataset.
   *
   * @var int
   */
  protected $cacheDuration = 10;

  /**
   * When the data was retrived.
   *
   * @var \DateTime
   */
  protected $updated;

  /**
   * GuzzleHttp\Client definition.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Drupal\Core\Cache\CacheBackendInterface definition.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheService;

  /**
   * Logger channel service object.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Reason mapper service.
   *
   * @var \Drupal\nidirect_school_closures\SchoolClosureReasonMapper
   */
  protected $reasonMapper;

  /**
   * Constructs a new ExceptionalClosuresSchoolClosuresService object.
   */
  public function __construct(HttpClient $http_client, CacheBackendInterface $cache, ConfigFactory $config_service, LoggerChannelFactory $logger, SchoolClosureReasonMapper $reason_mapper) {
    $this->httpClient = $http_client;
    $this->cacheService = $cache;
    $this->logger = $logger->get('nidirect_school_closures');
    $this->reasonMapper = $reason_mapper;

    // Fetch the config settings.
    $config = $config_service->get('nidirect_school_closures.settings');
    $this->url = $config->get('data_source_url') ?? $this->url;
    $this->cacheDuration = $config->get('cache_duration') ?? $this->cacheDuration;
    $this->maxAttempts = $config->get('max_attempts') ?? $this->maxAttempts;
  }

  /**
   * Last updated date.
   *
   * @return \DateTime
   *   Returns dataset last updated date.
   */
  public function getUpdated(): \DateTime {
    return $this->updated;
  }

  /**
   * Getter for data.
   *
   * @return array
   *   Returns closures dataset array.
   */
  public function getData(): array {
    return $this->data;
  }

  /**
   * Setter for the decoded JSON response.
   *
   * @param array $responseData
   *   Decoded JSON response containing school closure data.
   */
  public function setResponseData(array $responseData): void {
    $this->responseData = $responseData;
  }

  /**
   * Return error state.
   *
   * @return bool
   *   Returns the current error state.
   */
  public function hasErrors(): bool {
    return $this->error;
  }

  /**
   * Return closures data.
   *
   * @return array
   *   closures array.
   */
  public function getClosures(): array {
    // Reset error state.
    $this->error = FALSE;

    $cache = $this->cacheService->get('school_closures');

    // If we have cached data, check the expiry.
    if (!empty($cache)) {
      $this->data = $cache->data;
      // Round the cache timestamp up to an int as cache->set() uses microtime()
      // to generate a decimal timestamp, and we don't need that accuracy.
      $this->updated = date_timestamp_set(new \DateTime(), round($cache->created));

      $now = new \DateTime('now');
      $interval = $now->diff($this->updated);

      // If the cached data is stale, delete cache and call again.
      if ($interval->i >= $this->cacheDuration) {
        // Backup the cache in case we can't retrieve from the external service.
        $this->cacheBackup = $cache;
        $this->cacheService->delete('school_closures');
        $this->getClosures();
      }
    }
    else {
      // Fetch data from the web endpoint.
      $this->fetchData();

      // Process received data or attempt again.
      if (!empty($this->responseData)) {
        $this->processData();
        $this->updated = new \DateTime('now');
        // Cache the data indefinitely. The cache will be deleted based
        // on the cache duration setting in the config.
        $this->cacheService->set('school_closures', $this->data, CacheBackendInterface::CACHE_PERMANENT);
        $this->attempt = 1;
      }
      else {
        // Attempt to fetch data until configured maximum attempts to prevent
        // looping and PHP throwing an error when the source feed is down.
        if ($this->attempt < $this->maxAttempts) {
          $this->attempt++;
          $this->getClosures();
        }
        else {
          // If exhausted max attempts then reset attempt counter and try
          // fetching backup cached dataset.
          $this->attempt = 1;
          if (!empty($this->cacheBackup)) {
            // Cache the data indefinitely. The cache will be deleted based
            // on the cache duration setting in the config.
            $this->cacheService->set('school_closures', $this->cacheBackup, CacheBackendInterface::CACHE_PERMANENT);
            $this->logger->notice('Unable to update school closure data, reverting to cached data.');
            $this->getClosures();
          }
          else {
            // Warn if we can't retrieve data from the service or the cache.
            $this->logger->alert('Unable to update school closure data or revert to cached data.');
            $this->error = TRUE;
          }
        }
      }
    }

    return $this->data;
  }

  /**
   * Fetch and decode the source data as JSON.
   */
  protected function fetchData() {
    // If we have a URL call it and parse the results.
    if (!empty($this->url)) {
      try {
        $response = $this->httpClient->get($this->url);

        if ($response->getStatusCode() == 200) {
          $json_string = $response->getBody()->getContents();
          $this->responseData = json_decode($json_string, TRUE);
        }
      }
      catch (ClientException $e) {
        $this->logger->warning('Failed to fetch school closure data. ' . $e->getMessage());
      }
    }
  }

  /**
   * Process the decoded JSON data into array.
   */
  public function processData() {
    // If we don't have a closures element there was an issue. Note this is
    // distinct from an empty closures array, which just means there are no
    // closures currently in effect.
    if (!isset($this->responseData['closures']) || !is_array($this->responseData['closures'])) {
      $this->error = TRUE;
      return;
    }

    $this->data = [];

    // Each entry is an institution, which may have multiple closures. All of
    // a school's current closures are grouped together under that school,
    // rather than each closure appearing as its own repeated entry.
    foreach ($this->responseData['closures'] as $institution) {
      $name = $institution['institutionName'] ?? '';
      $location = $institution['address']['formattedAddress'] ?? '';

      if (empty($name) || empty($institution['closures'])) {
        continue;
      }

      $events = [];

      foreach ($institution['closures'] as $closureEvent) {
        if (empty($closureEvent['dateFrom'])) {
          continue;
        }

        $date = new \DateTime($closureEvent['dateFrom'], new \DateTimeZone('Europe/London'));
        $dateTo = !empty($closureEvent['dateTo']) ? new \DateTime($closureEvent['dateTo'], new \DateTimeZone('Europe/London')) : NULL;
        $reason = $this->reasonMapper->combine($closureEvent['reasons'] ?? []);

        $closure = new SchoolClosure($name, $location, $date, $reason, $dateTo);

        if ($closure->isExpired()) {
          continue;
        }

        $events[] = $closure->getData();
      }

      // Skip schools with no current closures.
      if (empty($events)) {
        continue;
      }

      usort($events, function ($a, $b) {
        return $a['date']->getTimestamp() - $b['date']->getTimestamp();
      });

      $this->data[] = [
        'name' => $name,
        'altname' => $events[0]['altname'],
        'location' => $location,
        'closures' => array_map(function ($event) {
          return [
            'date' => $event['date'],
            'dateTo' => $event['dateTo'],
            'reason' => $event['reason'],
          ];
        }, $events),
      ];
    }

    // Sort schools by their earliest current closure date.
    usort($this->data, function ($a, $b) {
      return $a['closures'][0]['date']->getTimestamp() - $b['closures'][0]['date']->getTimestamp();
    });

    $this->error = FALSE;
  }

}

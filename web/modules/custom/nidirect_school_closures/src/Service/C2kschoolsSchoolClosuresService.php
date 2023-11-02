<?php

namespace Drupal\nidirect_school_closures\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\nidirect_school_closures\SchoolClosure;
use Drupal\nidirect_school_closures\SchoolClosuresServiceInterface;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;

/**
 * Implementation of SchoolClosuresService using C2k service.
 */
class C2kschoolsSchoolClosuresService implements SchoolClosuresServiceInterface {

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
   * Parsed XML response from the service call.
   *
   * @var \SimpleXMLElement
   */
  protected $xml = NULL;

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
   * Constructs a new C2kschoolsSchoolClosuresService object.
   */
  public function __construct(HttpClient $http_client, CacheBackendInterface $cache, ConfigFactory $config_service, LoggerChannelFactory $logger) {
    $this->httpClient = $http_client;
    $this->cacheService = $cache;
    $this->logger = $logger->get('nidirect_school_closures');

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
   * Setter for XML.
   *
   * @param \SimpleXMLElement $xml
   *   XML Element containing school closure data.
   */
  public function setXml(\SimpleXMLElement $xml): void {
    $this->xml = $xml;
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
      if (!empty($this->xml)) {
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
   * Fetch and convert the source data to XML.
   */
  protected function fetchData() {
    // If we have a URL call it and parse the results.
    if (!empty($this->url)) {
      try {
        $response = $this->httpClient->get($this->url);

        if ($response->getStatusCode() == 200) {
          $xml_string = $response->getBody()->getContents();
          $this->xml = simplexml_load_string($xml_string);
        }
      }
      catch (ClientException $e) {
        $this->logger->warning('Failed to fetch school closure data. ' . $e->getMessage());
      }
    }
  }

  /**
   * Process the XML data into array.
   */
  public function processData() {
    // If we don't have a channel element there was an issue.
    if (empty($this->xml->channel)) {
      $this->error = TRUE;
      return;
    }

    // Process all closure XML elements.
    if (!empty($this->xml->channel->item)) {
      $this->data = [];

      foreach ($this->xml->channel->item as $item) {
        $title = utf8_decode($item->title);
        $description = utf8_decode($item->description);

        // Extract reason and date, skip if not matched.
        if (preg_match('/^(.*)<br\/><br\/>Closure takes place on (\d{2}\/\d{2}\/\d{4})$/', $description, $matches)) {
          $date = trim($matches[2]);

          $closure_date = explode('/', $date);
          $date_formatted = $closure_date[2] . '-' . $closure_date[1] . '-' . $closure_date[0];
          $date = new \DateTime($date_formatted, new \DateTimeZone('Europe/London'));

          $reason = trim($matches[1]);
        }
        else {
          continue;
        }

        // Extract name and location, skip if not matched.
        if (preg_match('/^(.*?),\s(.*)\(\d+\)$/', $title, $matches)) {
          $name = trim($matches[1]);
          $location = trim($matches[2]);
        }
        else {
          continue;
        }

        // Closure processing object.
        $closure = new SchoolClosure($name, $location, $date, $reason);

        if ($closure->isExpired()) {
          continue;
        }

        $this->data[] = $closure->getData();
      }

      // Sort the results by closure date.
      usort($this->data, function ($a, $b) {
        return $a['date']->getTimestamp() - $b['date']->getTimestamp();
      });

      $this->error = FALSE;
    }
  }

}

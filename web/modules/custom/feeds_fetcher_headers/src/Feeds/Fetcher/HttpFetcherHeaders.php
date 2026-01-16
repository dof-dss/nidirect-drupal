<?php

namespace Drupal\feeds_fetcher_headers\Feeds\Fetcher;

use Drupal\feeds\Exception\EmptyFeedException;
use Drupal\feeds\Feeds\Fetcher\HttpFetcher;
use Drupal\feeds\Result\HttpFetcherResult;
use Drupal\feeds\StateInterface;
use Drupal\feeds\Utility\Feed;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Symfony\Component\HttpFoundation\Response;
use Drupal\feeds\FeedInterface;

/**
 * Defines an HTTP fetcher.
 *
 * @FeedsFetcher(
 *   id = "httpfetcherheaders",
 *   title = @Translation("Download from URL additional Headers"),
 *   description = @Translation("Downloads data from a URL using Drupal's HTTP request handler with additional Headers."),
 *   form = {
 *     "configuration" = "Drupal\feeds\Feeds\Fetcher\Form\HttpFetcherForm",
 *     "feed" = "Drupal\feeds_fetcher_headers\Feeds\Fetcher\Form\HttpFetcherHeadersFeedForm",
 *   }
 * )
 */
class HttpFetcherHeaders extends HTTPFetcher {

  /**
   * {@inheritdoc}
   */
  public function defaultFeedConfiguration() {
    $default_configuration = parent::defaultConfiguration();
    $default_configuration['headers'] = '';
    $default_configuration['httpmethod'] = '';
    return $default_configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function fetch(FeedInterface $feed, StateInterface $state) {
    $sink = $this->fileSystem->tempnam('temporary://', 'feeds_http_fetcher');
    $sink = $this->fileSystem->realpath($sink);

    $response = $this->get($feed->getSource(), $sink, $this->getCacheKey($feed), $feed->getConfigurationFor($this)['headers'], $feed->getConfigurationFor($this)['httpmethod']);
    // @todo Handle redirects.
    // @codingStandardsIgnoreStart
    // $feed->setSource($response->getEffectiveUrl());
    // @codingStandardsIgnoreEnd

    // 304, nothing to see here.
    if ($response->getStatusCode() == Response::HTTP_NOT_MODIFIED) {
      $state->setMessage($this->t('The feed has not been updated.'));
      throw new EmptyFeedException();
    }

    return new HttpFetcherResult($sink, $response->getHeaders());
  }

  /**
   * Performs a GET request.
   *
   * @param string $url
   *   The URL to GET.
   * @param string $sink
   *   The location where the downloaded content will be saved. This can be a
   *   resource, path or a StreamInterface object.
   * @param string $cache_key
   *   (optional) The cache key to find cached headers. Defaults to false.
   * @param string $headers
   *   (optional) The headers or form fields. Defaults to ''.
   * @param string $method
   *   (optional) The HTTP method. Defaults to ''.
   *
   * @return \Guzzle\Http\Message\Response
   *   A Guzzle response.
   *
   * @throws \RuntimeException
   *   Thrown if the GET request failed.
   *
   * @see \GuzzleHttp\RequestOptions
   */
  protected function get($url, $sink, $cache_key = FALSE, $headers = '', $method = '') {
    $url = Feed::translateSchemes($url);

    $options = [RequestOptions::SINK => $sink];

    if ($headers !== '') {
      // Massage $headers to generate $options[RequestOptions::HEADERS] values.
      foreach (explode("\r\n", $headers) as $row) {
        if (preg_match('/(.*?): (.*)/', $row, $matches)) {
          if ($method == 'POST') {
            $options[RequestOptions::FORM_PARAMS][$matches[1]] = $matches[2];
          }
          else {
            $options[RequestOptions::HEADERS][$matches[1]] = $matches[2];
          }
        }
      }
    }

    // Add cached headers if requested.
    if ($cache_key && ($cache = $this->cache->get($cache_key))) {
      if (isset($cache->data['etag'])) {
        $options[RequestOptions::HEADERS]['If-None-Match'] = $cache->data['etag'];
      }
      if (isset($cache->data['last-modified'])) {
        $options[RequestOptions::HEADERS]['If-Modified-Since'] = $cache->data['last-modified'];
      }
    }

    try {
      if ($method == 'POST') {
        $response = $this->client->post($url, $options);
      }
      else {
        $response = $this->client->get($url, $options);
      }
    }
    catch (RequestException $e) {
      $args = [
        '%site' => $url,
        '%error' => $e->getMessage(),
        '%headers' => $headers,
      ];
      throw new \RuntimeException($this->t('The feed from %site seems to be broken because of error "%error". %headers', $args));
    }

    if ($cache_key) {
      $this->cache->set($cache_key, array_change_key_case($response->getHeaders()));
    }
    return $response;
  }

}

<?php

namespace Drupal\nidirect_hospital_waiting_times\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class HttpHeadersSubscriber implements EventSubscriberInterface {

  /**
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $currentRouteMatch;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(RouteMatchInterface $current_route_match, ConfigFactoryInterface $config_factory) {
    $this->currentRouteMatch = $current_route_match;
    $this->configFactory = $config_factory;
  }

  /**
   * Looks for a known render string output by the
   * [nidirect:hospital_emergency_waiting_times] token.
   *
   * If it's present, then we sync the surrogate-control header
   * value to a lower value on the pages it appears on so that
   * any hospital waiting times aren't cached for longer than
   * the refresh interval on the data feed.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event object from the event handler.
   */
  public function remoteSurrogateControlHeader(ResponseEvent $event) {
    $response = $event->getResponse();
    $content = $response->getContent();

    // Is there emergency-department-average-waiting-times render markup in
    // the response string?
    if (preg_match('/id="emergency-department-average-waiting-times"/', $content)) {
      $response->headers->remove('surrogate-control');
      // Replace with config value for cache expiration.
      $cache_duration = $this->configFactory->get('nidirect_hospital_waiting_times.settings')->get('cache_duration') ?? 10;

      // Convert minutes from config into seconds for surrogate-control TTL.
      $response->headers->set('surrogate-control', 'max-age=' . ($cache_duration * 60));
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // -100 weighting overrides other services such as those from
    // http_cache_control and fastly modules.
    $events[KernelEvents::RESPONSE][] = ['remoteSurrogateControlHeader', -100];
    return $events;
  }

}

<?php

namespace Drupal\nidirect_school_closures\EventSubscriber;

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
   * Looks for a specific school closures cache tag in the response
   * headers, because we can't tell otherwise if the token or list
   * has otherwise rendered.
   *
   * If it's present, then we sync the surrogate-control header
   * value to a lower value on the pages it appears on so that
   * any school closure updates aren't cached for longer than
   * the refresh interval is on the data feed.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event object from the event handler.
   */
  public function remoteSurrogateControlHeader(ResponseEvent $event) {
    // Only trigger when school closures token has rendered.
    // We have no visiblity of the final render arrays at this point
    // so we can either rely on x-drupal-cache-tags when in development
    // to see what has rendered, but in prod we don't want those header
    // values being shown so they become unusable here too.
    // What we can do is inspect the full HTML response and look
    // for a known render string. It's not perfect, but probably better
    // than leaving cache tag debug headers ON by default and doing some
    // kind of removal process in here; that'd be very confusing for
    // future maintenance and developers.
    $response = $event->getResponse();
    $content = $response->getContent();

    // Is there nidirect_school_closures render markup in the response string?
    if (preg_match('|<div id="school-closure-results">|', $content)) {
      $response->headers->remove('surrogate-control');
      // Replace with config value for school data feed expiration.
      $cache_duration = $this->configFactory->get('nidirect_school_closures.settings')->get('cache_duration') ?? 10;

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

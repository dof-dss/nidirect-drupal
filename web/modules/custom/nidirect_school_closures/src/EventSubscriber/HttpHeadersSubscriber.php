<?php

namespace Drupal\nidirect_school_closures\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber for handling school closure caching.
 */
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
   * Checks if response content contains rendered school closures and
   * adds a surrogate-control header using the school closures cache settings
   * to ensure the page containing it expires properly.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event object from the event handler.
   */
  public function remoteSurrogateControlHeader(ResponseEvent $event) {

    $response = $event->getResponse();
    $content = $response->getContent();

    // Check if the school closures markup (via token) is rendered on the output.
    if (preg_match('|<div id="school-closure-results">|', $content)) {
      $response->headers->remove('surrogate-control');
      $cache_duration = $this->configFactory->get('nidirect_school_closures.settings')->get('cache_duration') ?? 10;

      // Add the school closures cache age to the response.
      $response->headers->set('surrogate-control', 'max-age=' . ($cache_duration * 60));
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Float this handler so that it is called before other cache services.
    $events[KernelEvents::RESPONSE][] = ['remoteSurrogateControlHeader', -100];
    return $events;
  }

}

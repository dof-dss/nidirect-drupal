<?php

namespace Drupal\nidirect_common\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides block plugin definitions.
 *
 * @see \Drupal\nidirect_common\Plugin\Block\NidirectFeedbackmenuLinkDerivative
 */
class NidirectFeedbackmenuLinkDerivative extends DeriverBase implements ContainerDeriverInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static();
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    // Use a route to build a link to the site
    // feedback webform.
    $links = [];
    $links['nidirect_feedbackmenulink'] = [
      'title' => 'Feedback',
      'menu_name' => 'main',
      'route_name' => 'entity.node.canonical',
      'route_parameters' => [
        'node' => 2843,
      ],
      'weight' => 8,
    ] + $base_plugin_definition;
    return $links;
  }

}

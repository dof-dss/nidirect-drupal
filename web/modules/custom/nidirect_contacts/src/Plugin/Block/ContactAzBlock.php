<?php

namespace Drupal\nidirect_contacts\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'ContactAzBlock' block.
 *
 * @Block(
 *  id = "contact_az_block",
 *  admin_label = @Translation("Contacts A to Z"),
 * )
 */
class ContactAzBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\Core\Routing\CurrentRouteMatch definition.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * ContactAzBlock constructor.
   *
   * @param array $configuration
   *   Site configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $route_match
   *   Route match object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CurrentRouteMatch $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $links = [];
    $skip_link = '';

    $title = $this->t('Find contacts beginning with<span class="visually-hidden"> A to Z</span>...');

    if ($this->routeMatch->getRouteName() === 'nidirect_contacts.letter') {
      $letter = $this->routeMatch->getParameter('letter');
      $title = $this->t('Showing entries for :letter', [':letter' => strtoupper($letter)]);
      $skip_link = '<a href="#contact-links" class="skip-link visually-hidden focusable">';
      $skip_link .= t('Skip A to Z') . '</a>';
      $build['title'] = [
        '#markup' => $skip_link . '<h2 class="label visually-hidden" id="contacts-az--title">' . $title . '</h2>',
      ];
    }
    else {
      $skip_link = '<a href="#section--translation-help" class="skip-link visually-hidden focusable">';
      $skip_link .= t('Skip A to Z') . '</a>';
      $build['title'] = [
        '#markup' => $skip_link . '<h2 class="label" id="contacts-az--title">' . $title . '</h2>',
      ];
    }

    foreach (array_merge(range('a', 'z'), range('0', '9')) as $item) {
      $links[] = Link::createFromRoute(strtoupper($item), 'nidirect_contacts.letter', ['letter' => $item])
        ->toRenderable();
    }

    $build['contact_az_block'] = [
      '#theme' => 'item_list',
      '#items' => $links,
      '#attributes' => [
        'class' => 'az-facet-list',
        'id' => 'contacts-az',
        'aria-describedby' => 'contacts-az--title',
      ],
    ];

    return $build;
  }

}

<?php

namespace Drupal\nidirect_contacts\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\geolocation\MapProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'gmaps_lazy_load_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "gmaps_lazy_load_formatter",
 *   label = @Translation("Google Maps: Lazy Loader"),
 *   field_types = {
 *     "geolocation"
 *   }
 * )
 */
class GMapsLazyLoadFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The Google Maps provider.
   *
   * @var \Drupal\geolocation\MapProviderInterface
   */
  protected $gmapsProvider;

  /**
   * The Google Maps configuration.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $gmapsConfiguration;

  /**
   * Constructs a TimestampAgoFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\geolocation\MapProviderInterface $map_provider
   *   The Geolocation map provider.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The Drupal configuration factory.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, MapProviderInterface $map_provider, ConfigFactoryInterface $config) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->gmapsProvider = $map_provider;
    $this->gmapsConfiguration = $config->get('geolocation_google_maps.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('plugin.manager.geolocation.mapprovider')->getMapProvider('google_maps'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'zoom' => '10',
      'map_type' => 'roadmap',
      'placeholder' => 'empty',
      'link_text' => '',
      'map_width' => '300',
      'map_height' => '300',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {

    // Specify the map type for the Google map.
    $form['map_type'] = [
      '#title' => $this->t('Map type'),
      '#type' => 'select',
      '#options' => [
        'roadmap' => t('Road map'),
        'satellite' => t('Satellite'),
        'hybrid' => t('Hybrid'),
        'terrain' => t('Terrain'),
      ],
      '#default_value' => $this->getSetting('map_type'),
    ];

    // Specify the zoom level for the Google map.
    $form['zoom'] = [
      '#title' => $this->t('Zoom'),
      '#type' => 'number',
      '#min' => 1,
      '#max' => 22,
      '#default_value' => $this->getSetting('zoom'),
    ];

    // Options to render various placeholders until the container is visible
    // and the JS has loaded the map.
    $form['placeholder'] = [
      '#title' => $this->t('Placeholder'),
      '#type' => 'select',
      '#options' => [
        'empty' => t('Empty'),
        'link' => t('Link to Google map'),
        'static_map' => t('Static map'),
      ],
      '#default_value' => $this->getSetting('placeholder'),
    ];

    // Specify the text for the link placeholder.
    $form['link_text'] = [
      '#title' => $this->t('Link text'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('link_text'),
      '#states' => [
        'visible' => [
          ':input[name="fields[field_location][settings_edit_form][settings][placeholder]"]' => ['value' => 'link'],
        ],
      ],
    ];

    // Static map width in pixels.
    $form['map_width'] = [
      '#title' => $this->t('Map width (px)'),
      '#type' => 'number',
      '#min' => 60,
      '#max' => 2000,
      '#default_value' => $this->getSetting('map_width'),
      '#states' => [
        'visible' => [
          ':input[name="fields[field_location][settings_edit_form][settings][placeholder]"]' => ['value' => 'static_map'],
        ],
      ],
    ];

    // Static map height in pixels.
    $form['map_height'] = [
      '#title' => $this->t('Map height (px)'),
      '#type' => 'number',
      '#min' => 60,
      '#max' => 2000,
      '#default_value' => $this->getSetting('map_height'),
      '#states' => [
        'visible' => [
          ':input[name="fields[field_location][settings_edit_form][settings][placeholder]"]' => ['value' => 'static_map'],
        ],
      ],
    ];

    return $form + parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $summary_item = $this->t(
      'Map type: @maptype <br> Zoom: @zoom <br> Placeholder: @placeholder', [
        '@maptype' => $this->getSetting('map_type'),
        '@zoom' => $this->getSetting('zoom'),
        '@placeholder' => $this->getSetting('placeholder'),
      ]
    );

    // Additional summary information for the static map.
    if ($this->getSetting('placeholder') === 'static_map') {
      $summary_item .= $this->t(
        'Width: @widthpx Height: @heightpx', [
          '@width' => $this->getSetting('map_width'),
          '@height' => $this->getSetting('map_height'),
        ]
      );
    }

    $summary[] = $summary_item;

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $formatter_settings = $this->getSettings();

    foreach ($items as $delta => $item) {
      /** @var \Drupal\Core\Field\FieldItemInterface $item */
      // Map settings for use with container data attributes and
      // placeholder rendering.
      $lat = $item->get('lat')->getString() ?? 0;
      $lng = $item->get('lng')->getString() ?? 0;

      $map_settings = [
        'lat' => $lat,
        'lng' => $lng,
        'center' => $lat . ',' . $lng,
        'map_type' => $formatter_settings['map_type'],
        'zoom' => $formatter_settings['zoom'],
        'api_key' => $this->gmapsConfiguration->get('google_map_api_key'),
      ];

      // Container element from which the JS will extract the data
      // to build the map.
      $elements[$delta] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['gmap', 'gmap-lazy-load'],
          'id' => Html::getUniqueId('gmap-lazy-load'),
          'role' => 'application',
          'aria-roledescription' => t('Google Map'),
          'data-lat' => $map_settings['lat'],
          'data-lng' => $map_settings['lng'],
          'data-maptype' => $map_settings['map_type'],
          'data-zoom' => $map_settings['zoom'],
        ],
      ];

      // Render placeholder type.
      switch ($formatter_settings['placeholder']) {
        case 'static_map':
          $gmaps_provider_base = $this->gmapsProvider::$googleMapsApiUrlBase ?? '';
          $static_url = Url::fromUri($gmaps_provider_base . '/maps/api/staticmap', [
            'query' => [
              'center' => $map_settings['center'],
              'zoom' => $map_settings['zoom'],
              'maptype' => $map_settings['map_type'],
              'size' => $formatter_settings['map_width'] . 'x' . $formatter_settings['map_height'],
              'key' => $map_settings['api_key'],
            ],
          ]);

          $elements[$delta]['static_map'] = [
            '#type' => 'html_tag',
            '#tag' => 'img',
            '#attributes' => [
              'src' => $static_url->toString(),
            ],
          ];
          break;

        case 'link':
          $link_url = Url::fromUri('https://www.google.com/maps/search/', [
            'query' => [
              'api' => 1,
              'query' => $map_settings['center'],
            ],
          ]);

          $elements[$delta]['link'] = [
            '#title' => $formatter_settings['link_text'],
            '#type' => 'link',
            '#url' => $link_url,
          ];
          break;

        default:
          break;

      }

    }

    // Attach lazy load and the geolocation module Google API library.
    $elements['#attached']['library'][] = 'nidirect_contacts/gmaps_lazy_load';
    $elements['#attached']['library'][] = 'geolocation_google_maps/google';

    return $elements;
  }

  /**
   * Generate the output appropriate for one field item.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   One field item.
   *
   * @return string
   *   The textual output generated.
   */
  protected function viewValue(FieldItemInterface $item) {
    return nl2br(Html::escape($item->value ?? ''));
  }

}

<?php

namespace Drupal\nidirect_cold_weather_payments\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the Weather station entity.
 *
 * @ConfigEntityType(
 *   id = "weather_station",
 *   label = @Translation("Weather station"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\nidirect_cold_weather_payments\WeatherStationEntityListBuilder",
 *     "form" = {
 *       "add" = "Drupal\nidirect_cold_weather_payments\Form\WeatherStationEntityForm",
 *       "edit" = "Drupal\nidirect_cold_weather_payments\Form\WeatherStationEntityForm",
 *       "delete" = "Drupal\nidirect_cold_weather_payments\Form\WeatherStationEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\nidirect_cold_weather_payments\WeatherStationEntityHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "weather_station",
 *   config_export = {
 *     "id",
 *     "label",
 *     "postcodes",
 *     "uuid"
 *   },
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "postcodes" = "postcodes",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/weather_station/{weather_station}",
 *     "add-form" = "/admin/structure/weather_station/add",
 *     "edit-form" = "/admin/structure/weather_station/{weather_station}/edit",
 *     "delete-form" = "/admin/structure/weather_station/{weather_station}/delete",
 *     "collection" = "/admin/structure/weather_station"
 *   }
 * )
 */
class WeatherStationEntity extends ConfigEntityBase implements WeatherStationEntityInterface {

  /**
   * The Weather station ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Weather station label.
   *
   * @var string
   */
  protected $label;

  /**
   * The Weather station postcodes.
   *
   * @var string
   */
  protected $postcodes;

}

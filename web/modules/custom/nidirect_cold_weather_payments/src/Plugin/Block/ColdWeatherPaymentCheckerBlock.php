<?php

namespace Drupal\nidirect_cold_weather_payments\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\nidirect_cold_weather_payments\Form\ColdWeatherPaymentCheckerForm;

/**
 * Provides a 'ColdWeatherPaymentCheckerBlock' block.
 *
 * @Block(
 *  id = "cold_weather_payment_checker_block",
 *  admin_label = @Translation("Cold weather payment checker block"),
 *  category = @Translation("NI Direct"),
 * )
 */
class ColdWeatherPaymentCheckerBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $form = \Drupal::formBuilder()->getForm(ColdWeatherPaymentCheckerForm::class);

    $build[] = $form;

    return $build;
  }

}

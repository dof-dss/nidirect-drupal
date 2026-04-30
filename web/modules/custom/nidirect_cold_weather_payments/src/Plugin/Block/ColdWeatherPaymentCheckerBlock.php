<?php

namespace Drupal\nidirect_cold_weather_payments\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilder;
use Drupal\nidirect_cold_weather_payments\Form\ColdWeatherPaymentCheckerForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'ColdWeatherPaymentCheckerBlock' block.
 *
 * @Block(
 *  id = "cold_weather_payment_checker_block",
 *  admin_label = @Translation("Cold weather payment checker block"),
 *  category = @Translation("NI Direct"),
 * )
 */
final class ColdWeatherPaymentCheckerBlock extends BlockBase {

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * Creates a new instance of the block.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Form\FormBuilder $form_builder
   *   The form builder object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilder $form_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder'),
    );
  }

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
    $form = $this->formBuilder->getForm(ColdWeatherPaymentCheckerForm::class);

    $build[] = $form;

    return $build;
  }

}

<?php

namespace Drupal\nidirect_landing_pages\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a health conditions search and a-z block.
 *
 * @Block(
 *   id = "nidirect_landing_pages_health_conditions_search_and_a_z",
 *   admin_label = @Translation("Health Conditions Search and A-Z"),
 *   category = @Translation("Landing pages")
 * )
 */
class HealthConditionsSearchAndAZBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The block manager service.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $pluginManagerBlock;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a new HealthConditionsSearchAndAZBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Block\BlockManagerInterface $plugin_manager_block
   *   The plugin.manager.block service.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, BlockManagerInterface $plugin_manager_block, FormBuilderInterface $form_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->pluginManagerBlock = $plugin_manager_block;
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
      $container->get('plugin.manager.block'),
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    $view = Views::getView('health_conditions');
    $view->setDisplay('search_page');
    $view->initHandlers();
    $form_state = (new FormState())
      ->setStorage([
        'view' => $view,
        'display' => &$view->display_handler->display,
        'rerender' => TRUE,
      ])
      ->setMethod('get')
      ->setAlwaysProcess()
      ->disableRedirect();
    $form_state->set('rerender', NULL);
    $search_form = $this->formBuilder->buildForm('\Drupal\views\Form\ViewsExposedForm', $form_state);

    $health_condition_atoz = $this->pluginManagerBlock->createInstance('healthconditions_az_block', []);

    $build['search'] = $search_form;
    $build['atoz'] = $health_condition_atoz->build();

    return $build;
  }

}

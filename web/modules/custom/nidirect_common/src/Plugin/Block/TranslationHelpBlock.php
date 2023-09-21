<?php

namespace Drupal\nidirect_common\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides the Translation Help Block.
 *
 * @Block(
 *   id = "translation_help_block",
 *   admin_label = @Translation("Translation help link"),
 *   category = @Translation("Translation help link"),
 * )
 */
class TranslationHelpBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The path validator.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface
   */
  protected $pathValidator;

  /**
   * Creates a TranslationHelpBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Path\PathValidatorInterface $path_validator
   *   The path validator.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RequestStack $request_stack, PathValidatorInterface $path_validator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->requestStack = $request_stack;
    $this->pathValidator = $path_validator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack'),
      $container->get('path.validator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $translation_help_alias = '/articles/translation-help';
    $translation_help_url = $this->pathValidator->getUrlIfValid($translation_help_alias);

    $block_content = [
      '#attributes' => [
        'class' => ['section-translation-help'],
      ]
    ];

    if ($translation_help_url) {
      // Add translation help link to block content.
      $block_content['translation-help-link'] = [
        '#type' => 'link',
        '#title' => $this->t('How to translate this page'),
        '#url' => $translation_help_url,
        '#attributes' => [
          'class' => ['section-translation-help__link'],
        ],
      ];
    }
    else {
      // We can't show a link in the block - so show missing link message.
      $block_content['link-missing'] = [
        '#markup' => '<span>Translation help page (/articles/translation-help) missing!</span>'
      ];
    }

    return $block_content;

  }

}

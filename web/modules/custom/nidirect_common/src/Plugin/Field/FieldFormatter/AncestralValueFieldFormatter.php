<?php

namespace Drupal\nidirect_common\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field formatter "AncestralValueFieldFormatter".
 *
 * @FieldFormatter(
 *   id = "ancestral_value_field_formatter",
 *   label = @Translation("Ancestral value"),
 *   description = @Translation("Fetch value from the current field or a configured number of ancestor terms"),
 *   field_types = {
 *     "text",
 *     "text_long",
 *   }
 * )
 */
class AncestralValueFieldFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Core Route match service.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * Create an instance of ContentModerationStateFormatter.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityTypeManagerInterface $entity_type_manager, CurrentRouteMatch $route_match) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->entityTypeManager = $entity_type_manager;
    $this->routeMatch = $route_match;
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
      $container->get('entity_type.manager'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'ancestor_depth' => '2',
      'ancestors_only' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['ancestor_depth'] = [
      '#title' => $this->t('Ancestor depth'),
      '#description' => $this->t('The number of ancestors to try for a value.'),
      '#type' => 'number',
      '#min' => 1,
      '#max' => 10,
      '#default_value' => $this->getSetting('ancestor_depth'),
    ];

    $elements['ancestors_only'] = [
      '#title' => $this->t('Only display ancestors values'),
      '#description' => $this->t('Do not display value for current entity, only the ancestors.'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('ancestors_only'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('Ancestor depth: @ancestor_depth', ['@ancestor_depth' => $this->getSetting('ancestor_depth')]);
    $summary[] = $this->t('Ancestors only: @ancestors_only', ['@ancestors_only' => $this->getSetting('ancestors_only') ? $this->t('True') : $this->t('False')]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $settings = $this->getSettings();

    // Remove the items from the current entity if dealing with
    // only ancestral values.
    if ($items->count() && $settings['ancestors_only']) {
      foreach ($items as $key => $item) {
        $items->removeItem($key);
      }
    }

    // If this entity doesn't have a value for the field, try the
    // parent and grandparent entities which are identified from
    // the sub_theme field.
    if (!$items->count() || $settings['ancestors_only'] == '1') {
      $entity = $items->getEntity();
      $term = NULL;

      if ($entity->hasField('field_subtheme') && !$entity->get('field_subtheme')->isEmpty()) {
        $term = $entity->get('field_subtheme')->entity;
      }
      elseif ($entity instanceof Term) {
        $term = $entity;
      }

      if ($term) {
        // This issue https://www.drupal.org/node/2019905
        // prevents us from using ->loadParents() as we won't
        // retrieve the root term.
        $ancestors = array_values($this->entityTypeManager->getStorage('taxonomy_term')->loadAllParents($term->id()));

        // Remove the current term from the list of ancestors if the current
        // entity is a taxonomy term.
        if ($this->routeMatch->getParameter('taxonomy_term')) {
          array_shift($ancestors);
        }

        // Navigate the configured depth of ancestor terms.
        for ($i = 0; $i < $settings['ancestor_depth']; $i++) {
          if (array_key_exists($i, $ancestors)) {
            /** @var \Drupal\Core\Field\FieldItemListInterface $field */
            $field = $ancestors[$i]->get('field_additional_info');
            // Ignore below phpstan issue: unclear what specific class
            // or interface should be set to access a field iterator.
            // Call to an undefined method Drupal\Core\Field\FieldItemListInterface::getIterator().
            // @phpstan-ignore-next-line.
            $items = $field->getIterator();

            if ($items->count()) {
              break;
            }
          }
        }
      }
    }

    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        '#type' => 'processed_text',
        '#text' => $item->value,
        '#format' => $item->format,
        '#langcode' => $item->getLangcode(),
      ];
    }

    return $elements;
  }

}

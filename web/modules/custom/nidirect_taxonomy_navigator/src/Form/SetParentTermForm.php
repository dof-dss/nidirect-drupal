<?php

namespace Drupal\nidirect_taxonomy_navigator\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for setting the parent taxonomy term.
 */
class SetParentTermForm extends FormBase {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'set_parent_term_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $request = $this->getRequest();

    $tid = $this->getRouteMatch()->getParameter('taxonomy_term');
    $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($tid);

    if ($term === NULL) {
      $this->messenger()->addError('Unable to set parent as the term was not found.');
      $this->redirect('nidirect_taxonomy_navigator.taxonomy_navigator_controller')->send();
    }

    if ($route = $request->attributes->get(RouteObjectInterface::ROUTE_OBJECT)) {
      $page_title = $route->getDefault('_title');
      $route->setDefault('_title', $page_title . ' for ' . $term->label());
    }

    $term_tree = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree($term->bundle());

    // Build our select options array vid => name.
    $terms = [];
    foreach ($term_tree as $tree_term) {
      $terms[$tree_term->tid] = $tree_term->name;
    }

    $shs = [
      'settings' => [
        'required' => TRUE,
        'multiple' => FALSE,
        'anyLabel' => t('- Any -'),
        'anyValue' => 'All',
        'addNewLabel' => '',
        'force_deepest' => FALSE,
        'create_new_items' => FALSE,
        'create_new_levels' => FALSE,
        'display_node_count' => FALSE,
      ],
      'bundle' => $term->bundle(),
      'baseUrl' => 'shs-term-data',
      'cardinality' => 1,
      'parents' => [[['parent' => 0, 'defaultValue' => 'All']]],
      'defaultValue' => NULL,
    ];

    $form['vocabulary'] = [
      '#type' => 'hidden',
      '#value' => $term->bundle(),
    ];

    $form['set_as_top_level_term'] = [
      '#type' => 'radios',
      '#title' => $this->t('Set as top level term (no parents)?'),
      '#options' => [
        0 => $this->t('No'),
        1 => $this->t('Yes'),
      ],
      '#required' => TRUE,
    ];

    $form['term_field'] = [
      '#type' => 'container',
    ];

    $form['term_field']['widget'] = [
      '#type' => 'select',
      '#title' => t('Select a parent term'),
      '#key_column' => 'tid',
      '#field_parents' => [],
      '#field_name' => 'term_field',
      '#shs' => $shs,
      '#options' => $terms,
      '#attributes' => [
        'class' => ['shs-enabled'],
      ],
      '#states' => [
        'visible' => [
          ':input[name="set_as_top_level_term"]' => ['value' => 0],
        ],
      ],
      '#attached' => [
        'library' => ['shs/shs.form'],
      ],
      '#element_validate' => [
        [
          '\Drupal\shs\Plugin\Field\FieldWidget\OptionsShsWidget',
          'validateElement',
        ],
      ],
      '#after_build' => [
        [
          '\Drupal\shs\Plugin\Field\FieldWidget\OptionsShsWidget',
          'afterBuild',
        ],
      ],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
      '#attributes' => ['class' => ['button--primary']],
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => 'Cancel',
      '#url' => Url::fromUri(\Drupal::request()->getSchemeAndHttpHost() . \Drupal::request()->query->get('destination')),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $tid = $this->getRouteMatch()->getParameter('taxonomy_term');
    $form_values = $form_state->getValues();

    if ($form_values['set_as_top_level_term']) {
      $parent_tid = 0;
    }
    else {
      $parent_tid = $form_values['widget'][0]['tid'];
    }

    $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($tid);

    if ($term) {
      $term->set('parent', $parent_tid);
      $term->save();
    }

    $this->getRequest()->query->remove('destination');
    $form_state->setRedirect('nidirect_taxonomy_navigator.taxonomy_navigator_form', [
      'vocabulary' => $form_values['vocabulary'],
      'taxonomy_term' => $parent_tid,
    ],
    [
      'query' => [
        'highlight' => $tid,
      ],
      'fragment' => $tid,
    ]);
  }

}

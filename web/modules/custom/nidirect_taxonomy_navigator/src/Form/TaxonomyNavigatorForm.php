<?php

namespace Drupal\nidirect_taxonomy_navigator\Form;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\nidirect_taxonomy_navigator\TaxonomyNavigatorAccess;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays main Taxonomy Navigator list as a form.
 */
class TaxonomyNavigatorForm extends FormBase {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $dbConnection;

  /**
   * Drupal\Core\Extension\ModuleHandler definition.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * Drupal\Core\Session\AccountProxy definition.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->dbConnection = Database::getConnection('default', 'default');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'taxonomy_navigator_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $route_params = $this->getRouteMatch()->getParameters();
    $request = $this->getRequest();

    $highlight_tid = $request->query->get('highlight');

    $vocabulary = $route_params->get('vocabulary');

    $tid = $route_params->get('taxonomy_term') ?? 0;

    // User taxonomy permissions.
    $can_edit = TaxonomyNavigatorAccess::canEditTerms($vocabulary->id())->isAllowed();
    $can_delete = TaxonomyNavigatorAccess::canDeleteTerms($vocabulary->id())->isAllowed();
    $can_reorder = TaxonomyNavigatorAccess::canReorderTerms($vocabulary->id())->isAllowed();

    $form['vocabulary'] = [
      '#type' => 'hidden',
      '#value' => $vocabulary->id(),
    ];

    $form['term_search'] = [
      '#type' => 'fieldset',
      '#title' => $this
        ->t('Term search'),
      '#attributes' => [
        'class' => 'container-inline',
      ],
    ];

    $form['term_search']['term'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search for term'),
      '#title_display' => 'invisible',
      '#autocomplete_route_name' => 'nidirect_taxonomy_navigator.nidirect_taxonomy_navigator_search.autocomplete',
      '#autocomplete_route_parameters' => ['vocabulary' => $vocabulary->id()],
      '#description' => $this->t('Start typing to bring up a list of terms, select a term and press Enter to display.'),
    ];

    $form['term_search']['term_search_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
    ];

    $breadcrumb = new Breadcrumb();

    $links[] = Link::createFromRoute($vocabulary->label(), 'nidirect_taxonomy_navigator.taxonomy_navigator_form', ['vocabulary' => $vocabulary->id()]);

    if ($tid > 0) {
      // This issue https://www.drupal.org/node/2019905
      // prevents us from using ->loadParents() as we won't
      // retrieve the root term.
      $ancestors = array_reverse(array_values($this->entityTypeManager->getStorage("taxonomy_term")->loadAllParents($tid)));

      foreach ($ancestors as $ancestor) {
        $links[] = Link::createFromRoute($ancestor->label(), 'nidirect_taxonomy_navigator.taxonomy_navigator_form', [
          'vocabulary' => $vocabulary->id(),
          'taxonomy_term' => $ancestor->id(),
        ]);
      }
    }

    $breadcrumb->setLinks($links);

    // For performance reasons we won't load the term entities.
    $terms = $this->entityTypeManager->getStorage("taxonomy_term")->loadTree($vocabulary->id(), $tid, 1, FALSE);
    $group_class = 'group-order-weight';

    $form['terms'] = [
      '#type' => 'table',
      '#caption' => $breadcrumb->toRenderable(),
      '#header' => [
        $this->t('Name'),
        $this->t('Weight'),
        '',
        $this->t('Operations'),
      ],
      '#empty' => $this->t('No terms found.'),
      '#tableselect' => FALSE,
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => $group_class,
        ],
      ],
    ];

    if (!$can_reorder) {
      unset($form['terms']['#tabledrag']);
    }

    // Build rows.
    foreach ($terms as $key => $term) {
      $form['terms'][$key]['#attributes']['class'][] = 'draggable';
      $form['terms'][$key]['#attributes']['id'][] = $term->tid;
      $form['terms'][$key]['#weight'] = $term->weight;
      if ($highlight_tid == $term->tid) {
        $form['terms'][$key]['#attributes']['style'][] = "background-color: lemonChiffon";
      }

      $form['terms'][$key]['name'] = [
        '#title' => $term->name,
        '#type' => 'link',
        '#url' => Url::fromRoute('nidirect_taxonomy_navigator.taxonomy_navigator_form', [
          'vocabulary' => $vocabulary->id(),
          'taxonomy_term' => $term->tid,
        ]),
      ];

      $form['terms'][$key]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', ['@title' => $term->name]),
        '#title_display' => 'invisible',
        '#default_value' => $term->weight,
        '#attributes' => ['class' => [$group_class]],
        '#disabled' => !$can_reorder,
      ];

      $form['terms'][$key]['tid'] = [
        '#type' => 'hidden',
        '#value' => $term->tid,
      ];

      $form['terms'][$key]['operations'] = [
        '#type' => 'operations',
        '#links' => [],
      ];

      $form['terms'][$key]['operations']['#links']['view'] = [
        'title' => t('View'),
        'url' => Url::fromRoute('entity.taxonomy_term.canonical', ['taxonomy_term' => $term->tid]),
      ];

      if ($can_edit) {
        $form['terms'][$key]['operations']['#links']['edit'] = [
          'title' => t('Edit'),
          'url' => Url::fromRoute('entity.taxonomy_term.edit_form', ['taxonomy_term' => $term->tid], ['query' => $this->getDestinationArray()]),
        ];

        $form['terms'][$key]['operations']['#links']['set_parent'] = [
          'title' => t('Set parent'),
          'url' => Url::fromRoute('nidirect_taxonomy_navigator.set_parent_term_form', [
            'vocabulary' => $vocabulary->id(),
            'taxonomy_term' => $term->tid,
          ],
          [
            'query' => $this->getDestinationArray(),
          ]),
        ];
      }

      if ($can_delete) {
        $form['terms'][$key]['operations']['#links']['delete'] = [
          'title' => t('Delete'),
          'url' => Url::fromRoute('entity.taxonomy_term.delete_form', ['taxonomy_term' => $term->tid], ['query' => $this->getDestinationArray()]),
        ];
      }

      // Add cache tag to assist with propagating changes across the site's
      // themeable things.
      $form['#cache']['tags'][] = 'taxonomy_term:' . $term->tid;
    }

    if ($can_reorder) {
      $form['actions'] = ['#type' => 'actions'];
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Expire cache tags.
    Cache::invalidateTags($form['#cache']['tags']);

    $form_values = $form_state->getValues();

    if (empty($form_values['term'])) {
      $form_values = $form_state->getValues();
      $terms = $form_values['terms'];
      foreach ($terms as $term) {
        // Todo: Improve the performance of updating weight.
        $this->dbConnection->update('taxonomy_term_field_data')
          ->fields(['weight' => $term['weight']])
          ->condition('tid', $term['tid'], '=')
          ->execute();
      }
    }
    else {
      $tid = EntityAutocomplete::extractEntityIdFromAutocompleteInput($form_values['term']);

      $ancestors = array_values($this->entityTypeManager->getStorage("taxonomy_term")->loadAllParents($tid));

      if (count($ancestors) > 1) {
        array_shift($ancestors);
        $parent = current($ancestors);
        $form_state->setRedirect('nidirect_taxonomy_navigator.taxonomy_navigator_form', [
          'vocabulary' => $form_values['vocabulary'],
          'taxonomy_term' => $parent->id(),
        ],
        [
          'query' => ['highlight' => $tid],
          'fragment' => $tid,
        ]);
      }
      else {
        $form_state->setRedirect('nidirect_taxonomy_navigator.taxonomy_navigator_form', [
          'vocabulary' => $form_values['vocabulary'],
        ],
        [
          'query' => [
            'highlight' => $tid,
          ],
          'fragment' => $tid,
        ]);
      }

    }
  }

}

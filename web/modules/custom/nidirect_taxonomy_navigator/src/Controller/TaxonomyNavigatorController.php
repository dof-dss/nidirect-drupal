<?php

namespace Drupal\nidirect_taxonomy_navigator\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Url;
use Drupal\nidirect_taxonomy_navigator\TaxonomyNavigatorAccess;
use Drupal\taxonomy\Entity\Vocabulary;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for displaying the taxonomy navigator.
 */
class TaxonomyNavigatorController extends ControllerBase {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Routing\RouteMatchInterface definition.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $currentRouteMatch;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->currentRouteMatch = $container->get('current_route_match');
    return $instance;
  }

  /**
   * Index.
   */
  public function index() {

    $vocabularies = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->loadMultiple();
    $administer_taxonomies = TaxonomyNavigatorAccess::canAdministerVocabularies()->isAllowed();

    $build['vocabularies'] = [
      '#type' => 'table',
      '#header' => [$this->t('Name')],
      '#empty' => $this->t('No vocabularies found.'),
      '#tableselect' => FALSE,
    ];

    if ($administer_taxonomies) {
      $build['vocabularies']['#header'][] = $this->t('Operations');
    }

    foreach ($vocabularies as $vocabulary) {

      if (TaxonomyNavigatorAccess::canViewTerms($vocabulary->id())->isForbidden()) {
        continue;
      }

      $build['vocabularies'][$vocabulary->id()]['name'] = [
        '#title' => $vocabulary->label(),
        '#type' => 'link',
        '#url' => Url::fromRoute('nidirect_taxonomy_navigator.taxonomy_navigator_form', [
          'vocabulary' => $vocabulary->id(),
        ]),
      ];

      if ($administer_taxonomies) {
        $build['vocabularies'][$vocabulary->id()]['operations'] = [
          '#type' => 'operations',
          '#links' => [],
        ];

        $build['vocabularies'][$vocabulary->id()]['operations']['#links']['overview'] = [
          'title' => t('Overview'),
          'url' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $vocabulary->id()], ['query' => $this->getDestinationArray()]),
        ];

        $build['vocabularies'][$vocabulary->id()]['operations']['#links']['edit'] = [
          'title' => t('Edit'),
          'url' => Url::fromRoute('entity.taxonomy_vocabulary.edit_form', ['taxonomy_vocabulary' => $vocabulary->id()], ['query' => $this->getDestinationArray()]),
        ];

        $build['vocabularies'][$vocabulary->id()]['operations']['#links']['fields'] = [
          'title' => t('Manage fields'),
          'url' => Url::fromRoute('entity.taxonomy_term.field_ui_fields', ['taxonomy_vocabulary' => $vocabulary->id()], ['query' => $this->getDestinationArray()]),
        ];

        $build['vocabularies'][$vocabulary->id()]['operations']['#links']['form'] = [
          'title' => t('Manage form display'),
          'url' => Url::fromRoute('entity.entity_form_display.taxonomy_term.default', ['taxonomy_vocabulary' => $vocabulary->id()], ['query' => $this->getDestinationArray()]),
        ];

        $build['vocabularies'][$vocabulary->id()]['operations']['#links']['display'] = [
          'title' => t('Manage display'),
          'url' => Url::fromRoute('entity.entity_view_display.taxonomy_term.default', ['taxonomy_vocabulary' => $vocabulary->id()], ['query' => $this->getDestinationArray()]),
        ];

        $build['vocabularies'][$vocabulary->id()]['operations']['#links']['delete'] = [
          'title' => t('Delete'),
          'url' => Url::fromRoute('entity.taxonomy_vocabulary.delete_form', ['taxonomy_vocabulary' => $vocabulary->id()], ['query' => $this->getDestinationArray()]),
        ];
      }
    }

    return $build;
  }

  /**
   * Provides title callback for vocabulary navigator .
   *
   * @param \Drupal\taxonomy\Entity\Vocabulary $vocabulary
   *   The Vocabulary entity.
   *
   * @return string|null
   *   The title for the entity view page, if an entity was found.
   */
  public function navigatorVocabularyTitle(Vocabulary $vocabulary) {
    return $this->t('@vocabulary', ['@vocabulary' => $vocabulary->label()]);
  }

  /**
   * Taxonomy term search autocomplete callback.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A HTTP Request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON object of matching terms.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function searchAutocomplete(Request $request) {

    $input = Xss::filter($request->query->get('q'));
    $vocabulary = $this->currentRouteMatch->getParameter('vocabulary');

    $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');

    $query = $termStorage->getQuery()
      ->condition('vid', $vocabulary->id())
      ->condition('name', $input, 'CONTAINS')
      ->sort('name', 'DESC')
      ->accessCheck(TRUE)
      ->range(0, 25);

    $ids = $query->execute();
    $terms = $ids ? $termStorage->loadMultiple($ids) : [];

    $results = [];
    foreach ($terms as $term) {

      $results[] = [
        'value' => EntityAutocomplete::getEntityLabels([$term]),
        'label' => $term->label(),
      ];
    }

    return new JsonResponse($results);
  }

}

<?php

namespace Drupal\Tests\nidirect_related_content\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\flag\Entity\Flag;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Views;

/**
 * @coversDefaultClass Drupal\nidirect_related_content\RelatedContentManager
 *
 * This test currently doesn't work but has been included to pick up at a later
 * point. Main sticking point is getting with the Views inside the Related
 * Content service working. The same Views are loaded and able to run from
 * within this test.
 *
 * @group nidirect
 * @group nidirect_related_content
 */
class RelatedContentTest extends ViewsKernelTestBase {
  use NodeCreationTrait;
  use ContentTypeCreationTrait;
  use UserCreationTrait;

  /**
   * Set Strict config Schema status.
   *
   * @var bool
   */
  protected $strictConfigSchema;

  /**
   * Related content service.
   *
   * @var \Drupal\nidirect_related_content\RelatedContentManager
   */
  protected $relatedContentManager;

  /**
   * {@inheritdoc}
   */
  public static $testViews = [
    'related_content_manager__content',
    'related_content_manager__terms'
  ];

  /**
   * Module machine name.
   *
   * @var array
   */
  public static $modules = [
    'nidirect_related_content',
    'taxonomy',
    'node',
    'user',
    'views',
    'text',
    'field',
    'system',
    'flag',
    'book'
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp($import_test_views = TRUE): void {
    parent::setUp($import_test_views);

    $this->installConfig(['nidirect_related_content']);
    $this->installConfig(['flag']);
    $this->installConfig(['book']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('flagging');
    $this->installSchema('user', 'users_data');
    $this->installSchema('flag', ['flag_counts']);
    $this->installSchema('book', ['book']);

    $this->relatedContentManager = \Drupal::service('nidirect_related_content.manager');
  }

  /**
   * Test related content manager service.
   */
  /*
  public function testSubThemeRelated() {
  $this->createThemeVocab();
  $this->createNodeTypes();

  $this->createUser([], 'admin', TRUE);

  $taxonomy_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

  $term = $taxonomy_storage->loadByProperties(['name' => 'Motoring']);

  $term = current($term);

  $node1 = Node::create([
  'type' => 'article',
  'title' => 'Motoring article 1',
  'field_subtheme' => [$term->id()],
  ]);
  $node1->save();

  $node2 = Node::create([
  'type' => 'article',
  'title' => 'Motoring article 2',
  'field_subtheme' => [$term->id()],
  ]);
  $node2->save();

  // For dev/testing purposes
  $view = Views::getView('related_content_manager__content');
  $view->setDisplay('by_supplementary_term');
  $view->setArguments([$term->id()]);
  $view->execute();

  $results = $this->relatedContentManager->getSubThemes()->forTheme($term->id())->asArray();

  self::assertEquals($term->id(), $node1->get('field_subtheme')->getString());
  }
   */

  /**
   * Create a Theme vocabulary and terms.
   */
  private function createThemeVocab() {
    $vocabulary = Vocabulary::create([
      'name' => 'Site themes',
      'vid' => 'site_themes',
    ]);
    $vocabulary->save();

    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $root = $term_storage->create([
      'name' => 'Motoring',
      'vid' => $vocabulary->id()
    ]);
    $root->save();

    $parent1 = $term_storage->create([
      'name' => 'MOT and vehicle testing',
      'vid' => $vocabulary->id(),
      'parent' => $root->id(),
    ]);
    $parent1->save();

    $parent2 = $term_storage->create([
      'name' => 'Road safety',
      'vid' => $vocabulary->id(),
      'parent' => $root->id(),
    ]);
    $parent2->save();

    // Motoring child terms.
    $parent1_child1 = $term_storage->create([
      'name' => 'About the MOT scheme',
      'vid' => $vocabulary->id(),
      'parent' => $parent1->id(),
    ]);
    $parent1_child1->save();

    $parent1_child2 = $term_storage->create([
      'name' => 'Other tests DVA carries out',
      'vid' => $vocabulary->id(),
      'parent' => $parent1->id(),
    ]);
    $parent1_child2->save();

    $parent1_child3 = $term_storage->create([
      'name' => 'Types of vehicles which require a test',
      'vid' => $vocabulary->id(),
      'parent' => $parent1->id(),
    ]);
    $parent1_child3->save();

    $parent1_child4 = $term_storage->create([
      'name' => 'The test centre',
      'vid' => $vocabulary->id(),
      'parent' => $parent1->id(),
    ]);
    $parent1_child4->save();

    // Road safety child terms.
    $parent2_child1 = $term_storage->create([
      'name' => 'Drink and drugs',
      'vid' => $vocabulary->id(),
      'parent' => $parent2->id(),
    ]);
    $parent2_child1->save();

    $parent2_child2 = $term_storage->create([
      'name' => 'Road safety education resources',
      'vid' => $vocabulary->id(),
      'parent' => $parent2->id(),
    ]);
    $parent2_child2->save();

    $parent3_child2_child1 = $term_storage->create([
      'name' => 'Road safety for post primary school children',
      'vid' => $vocabulary->id(),
      'parent' => $parent2_child2->id(),
    ]);
    $parent3_child2_child1->save();

    $parent3_child2_child2 = $term_storage->create([
      'name' => 'Road safety for primary school children',
      'vid' => $vocabulary->id(),
      'parent' => $parent2_child2->id(),
    ]);
    $parent3_child2_child2->save();

  }

  /**
   * Create Article bundle and site themes field.
   */
  private function createNodeTypes() {

    $bundles = [
      'application',
      'article',
      'external_link',
      'health_condition',
      'landing_page',
      'publication',
      'webform',
    ];

    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_subtheme',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'taxonomy_term',
      ],
    ])->save();

    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_site_themes',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'taxonomy_term',
      ],
    ])->save();

    foreach ($bundles as $type) {
      NodeType::create([
        'type' => $type,
      ])->save();

      FieldConfig::create([
        'field_name' => 'field_subtheme',
        'entity_type' => 'node',
        'bundle' => $type,
      ])->save();

      FieldConfig::create([
        'field_name' => 'field_site_themes',
        'entity_type' => 'node',
        'bundle' => $type,
      ])->save();
    }

    $flag_hide_content = Flag::create([
      'id' => 'hide_content',
      'label' => 'Hide content',
      'entity_type' => 'node',
      'bundles' => $bundles,
      'flag_type' => 'entity:node',
      'link_type' => 'ajax_link',
      'flagTypeConfig' => [],
      'linkTypeConfig' => [],
    ]);
    $flag_hide_content->save();

    $flag_display_on_landing_pages = Flag::create([
      'id' => 'display_on_landing_pages',
      'label' => 'Show link on landing pages and menus',
      'entity_type' => 'node',
      'bundles' => ['publication'],
      'flag_type' => 'entity:node',
      'link_type' => 'ajax_link',
      'flagTypeConfig' => [],
      'linkTypeConfig' => [],
    ]);
    $flag_display_on_landing_pages->save();

  }

}

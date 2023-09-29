<?php

namespace Drupal\Tests\nidirect_gp\Functional;

use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests for GP practice.
 *
 * @group nidirect_gp
 * @group nidirect
 */
class GPPracticeTest extends BrowserTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['nidirect_common', 'node'];

  /**
   * Drupal\Tests\BrowserTestBase::$defaultTheme is required. See
   * https://www.drupal.org/node/3083055, which includes recommendations
   * on which theme to use.
   *
   * @var string
   */
  protected $defaultTheme = 'classy';

  /**
   * Use install profile so that we have all content types, modules etc.
   *
   * @var string
   */
  protected $profile = 'test_profile';

  /**
   * Set to TRUE to strict check all configuration saved.
   *
   * Need to set to FALSE here because some contrib modules have a schema in
   * config/schema that does not match the actual settings exported
   * (eu_cookie_compliance and google_analytics_counter, I'm looking at you).
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

  /**
   * Tests the behavior when creating the node with two fields.
   */
  public function testVanillaNodeCreate() {
    // Create a node to view.
    $node = $this->drupalCreateNode([
      'type' => 'gp_practice',
      'field_gp_practice_name' => [['value' => 'Practice']],
      'field_gp_surgery_name' => [['value' => 'Surgery']],
    ]);
    $this->assertTrue(Node::load($node->id()), 'Node created.');

    $this->assertTrue(Node::load($node->id()), 'Node created.');
    $new_node = Node::load($node->id());
    // Node title should have been automatically set to include
    // both fields.
    $this->assertEquals('Surgery - Practice', $new_node->getTitle());
  }

  /**
   * Tests the behavior when creating the node with one field.
   */
  public function testOneFieldNodeCreate() {
    // Create a node with just one field filled in.
    $node = $this->drupalCreateNode([
      'type' => 'gp_practice',
      'field_gp_practice_name' => [['value' => 'Practice']],
    ]);
    $this->assertTrue(Node::load($node->id()), 'Node created.');
    $new_node = Node::load($node->id());
    // Node title should have been automatically set to include
    // the practice name.
    $this->assertEquals('Practice', $new_node->getTitle());

    // Create a node with the other field filled in.
    $node = $this->drupalCreateNode([
      'type' => 'gp_practice',
      'field_gp_surgery_name' => [['value' => 'Surgery']],
    ]);
    $this->assertTrue(Node::load($node->id()), 'Node created.');
    $new_node = Node::load($node->id());
    // Node title should have been automatically set to include
    // the practice name.
    $this->assertEquals('Surgery', $new_node->getTitle());
  }

}

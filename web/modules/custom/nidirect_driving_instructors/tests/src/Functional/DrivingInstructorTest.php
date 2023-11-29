<?php

namespace Drupal\Tests\nidirect_driving_instructors\Functional;

use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests Driving Instructor title generation.
 *
 * @group nidirect_driving_instructors
 * @group nidirect
 */
class DrivingInstructorTest extends BrowserTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['node', 'nidirect_driving_instructors'];

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
   * Tests the behavior when creating the node.
   */
  public function testNodeCreate() {
    // Create a node to view.
    $node = $this->drupalCreateNode([
      'type' => 'driving_instructor',
      'field_di_firstname' => [['value' => 'Firstname']],
      'field_di_lastname' => [['value' => 'Lastname']],
      'field_di_adi_no' => [['value' => '222']],
    ]);
    $this->assertTrue(Node::load($node->id()), 'Node created.');
    $new_node = Node::load($node->id());
    // Node title should have been automatically set to include
    // all three fields.
    $this->assertEquals('Firstname Lastname (ADI No. 222)', $new_node->getTitle());
  }

}

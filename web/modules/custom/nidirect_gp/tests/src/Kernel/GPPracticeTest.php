<?php

namespace Drupal\Tests\nidirect_gp\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;

/**
 * Tests Driving Instructor title generation.
 *
 * @group nidirect_gp
 * @group nidirect
 */
class GPPracticeTest extends EntityKernelTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['node', 'nidirect_gp', 'nidirect_common'];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installConfig('nidirect_gp');
  }

  /**
   * Tests the behavior when creating the node with two fields.
   */
  public function testVanillaNodeCreate() {
    // Create a node to view.
    $node = Node::create([
      'type' => 'gp_practice',
      'field_gp_practice_name' => [['value' => 'Practice']],
      'field_gp_surgery_name' => [['value' => 'Surgery']],
    ]);
    $node->save();
    $this->assertEquals('Surgery - Practice', $node->getTitle());
  }

  /**
   * Tests the behavior when creating the node with one field.
   */
  public function testOneFieldNodeCreate() {
    // Create a node with just one field filled in.
    $node = Node::create([
      'type' => 'gp_practice',
      'field_gp_practice_name' => [['value' => 'Practice']],
    ]);
    $node->save();
    $this->assertEquals('Practice', $node->getTitle());

    // Create a node with the other field filled in.
    $node = Node::create([
      'type' => 'gp_practice',
      'field_gp_practice_name' => [['value' => 'Surgery']],
    ]);
    $node->save();
    $this->assertEquals('Surgery', $node->getTitle());
  }

}

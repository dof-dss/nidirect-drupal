<?php

namespace Drupal\Tests\nidirect_gp\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;

/**
 * Tests GP practice title generation.
 *
 * @group nidirect_gp
 * @group nidirect
 */
class GPPracticeTest extends EntityKernelTestBase {

  /**
   * Modules to install.
   *
   * @var string[]
   */
  protected static $modules = [
    // Core modules commonly required for node entities + fields in kernel tests.
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',

    // Your modules.
    'nidirect_common',
    'nidirect_gp',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Schemas required for creating/loading nodes.
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');

    // Install module config (content type, fields, etc) if provided as config/install.
    $this->installConfig([
      'nidirect_common',
      'nidirect_gp',
    ]);
  }

  /**
   * Tests the behavior when creating the node with two fields.
   */
  public function testVanillaNodeCreate(): void {
    $node = Node::create([
      'type' => 'gp_practice',
      'field_gp_practice_name' => [['value' => 'Practice']],
      'field_gp_surgery_name' => [['value' => 'Surgery']],
    ]);
    $node->save();

    $this->assertSame('Surgery - Practice', $node->getTitle());
  }

  /**
   * Tests the behavior when creating the node with one field.
   */
  public function testOneFieldNodeCreate(): void {
    // Practice name only.
    $node = Node::create([
      'type' => 'gp_practice',
      'field_gp_practice_name' => [['value' => 'Practice']],
    ]);
    $node->save();

    $this->assertSame('Practice', $node->getTitle());

    // Surgery name only.
    $node = Node::create([
      'type' => 'gp_practice',
      'field_gp_surgery_name' => [['value' => 'Surgery']],
    ]);
    $node->save();

    $this->assertSame('Surgery', $node->getTitle());
  }

}

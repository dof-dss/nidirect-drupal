<?php

namespace Drupal\Tests\nidirect_driving_instructors\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;

/**
 * Tests Driving Instructor title generation.
 *
 * @group nidirect_driving_instructors
 * @group nidirect
 */
class DrivingInstructorTest extends EntityKernelTestBase {

  /**
   * Modules to install.
   * @var string[]
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'nidirect_driving_instructors',
  ];

  /**
   * @inheritDoc
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['nidirect_driving_instructors']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
  }

  public function testNodeCreateSetsTitle(): void {
    $node = Node::create([
      'type' => 'driving_instructor',
      'field_di_firstname' => [['value' => 'Firstname']],
      'field_di_lastname' => [['value' => 'Lastname']],
      'field_di_adi_no' => [['value' => '222']],
    ]);
    $node->save();

    $reloaded = Node::load($node->id());
    $this->assertNotNull($reloaded, 'Node created.');
    $this->assertSame('Firstname Lastname (ADI No. 222)', $reloaded->getTitle());
  }

}

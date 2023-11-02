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
   * {@inheritdoc}
   */
  public static $modules = [
    'user',
    'system',
    'node',
    'field',
    'text',
    'filter',
    'entity_test',
    'nidirect_driving_instructors',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installConfig('nidirect_driving_instructors');
  }

  /**
   * Tests that the correct title is generated when creating a new node.
   */
  public function testNodeCreate() {
    // Create a driving instructor.
    $node = Node::create([
      'type' => 'driving_instructor',
      'field_di_firstname' => [['value' => 'Firstname']],
      'field_di_lastname' => [['value' => 'Lastname']],
      'field_di_adi_no' => [['value' => '222']],
    ]);
    $node->save();
    // Title should have been automatically set to a combination of fields.
    $this->assertEquals('Firstname Lastname (ADI No. 222)', $node->getTitle());
  }

}

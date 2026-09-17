<?php

namespace Drupal\Tests\nidirect_school_closures\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\nidirect_school_closures\SchoolClosureReasonMapper;

/**
 * @coversDefaultClass Drupal\nidirect_school_closures\SchoolClosureReasonMapper
 *
 * @group nidirect_school_closures
 * @group nidirect
 */
class SchoolClosureReasonMapperTest extends KernelTestBase {

  /**
   * The reason mapper.
   *
   * @var \Drupal\nidirect_school_closures\SchoolClosureReasonMapper
   */
  protected $mapper;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->mapper = new SchoolClosureReasonMapper();
  }

  /**
   * Test a known reasonTypeId returns its mapped fragment.
   */
  public function testGetFragmentReturnsMappedValue() {
    $expected = 'adverse weather';

    $output = $this->mapper->getFragment(1, 'Adverse weather');

    $this->assertEquals($expected, $output);
  }

  /**
   * Test an unmapped reasonTypeId falls back to the API's own text.
   */
  public function testGetFragmentFallsBackForUnmappedId() {
    $expected = 'other';

    $output = $this->mapper->getFragment(999, 'Other');

    $this->assertEquals($expected, $output);
  }

  /**
   * Test a single reason is combined without joining words.
   */
  public function testCombineSingleReason() {
    $reasons = [
      ['reasonTypeId' => 1, 'reasonType' => 'Adverse weather'],
    ];

    $expected = 'due to adverse weather.';

    $output = $this->mapper->combine($reasons);

    $this->assertEquals($expected, $output);
  }

  /**
   * Test two reasons are joined with "and".
   */
  public function testCombineTwoReasons() {
    $reasons = [
      ['reasonTypeId' => 1, 'reasonType' => 'Adverse weather'],
      ['reasonTypeId' => 2, 'reasonType' => 'Use as a polling station'],
    ];

    $expected = 'due to adverse weather and use as a polling station for an election.';

    $output = $this->mapper->combine($reasons);

    $this->assertEquals($expected, $output);
  }

  /**
   * Test three or more reasons are joined with an Oxford comma.
   */
  public function testCombineThreeReasons() {
    $reasons = [
      ['reasonTypeId' => 1, 'reasonType' => 'Adverse weather'],
      ['reasonTypeId' => 2, 'reasonType' => 'Use as a polling station'],
      ['reasonTypeId' => 999, 'reasonType' => 'Other'],
    ];

    $expected = 'due to adverse weather, use as a polling station for an election and other.';

    $output = $this->mapper->combine($reasons);

    $this->assertEquals($expected, $output);
  }

}

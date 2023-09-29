<?php

namespace Drupal\Tests\nidirect_school_closures\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\nidirect_school_closures\SchoolClosure;

/**
 * @coversDefaultClass Drupal\nidirect_school_closures\SchoolClosure
 *
 * @group nidirect_school_closures
 * @group nidirect
 */
class SchoolClosuresTest extends KernelTestBase {

  /**
   * Today's date.
   *
   * @var \DateTime
   */
  protected $today;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->today = new \DateTime('now', new \DateTimeZone('Europe/London'));
    // Reset the clock to avoid issues with time comparisons.
    $this->today->setTime(0, 0, 0);
  }

  /**
   * Return todays date.
   */
  private function today() {
    return $this->today;
  }

  /**
   * Test school names with accented characters.
   *
   * Checks school name has alternative non-accented version.
   */
  public function testAltName() {
    $name = 'Bunscoil Baile Mór';
    $location = 'Belfast';
    $date = $this->today();
    $reason = '';

    $expected = 'Bunscoil Baile Mor';

    $closure = new SchoolClosure($name, $location, $date, $reason);
    $output = $closure->getData();

    $this->assertEquals($expected, $output['altname']);
  }

  /**
   * School location test.
   *
   * Test location matches original and town is not removed if absent
   * from school name.
   */
  public function testLocation() {
    $name = 'All Saints Primary School';
    $location = 'Portadown, County Armagh';
    $date = $this->today();
    $reason = '';

    // The location should not change.
    $expected = $location;

    $closure = new SchoolClosure($name, $location, $date, $reason);
    $output = $closure->getData();

    $this->assertEquals($expected, $output['location']);
  }

  /**
   * Test location has town removed if present in school name.
   */
  public function testLocationWithMatchingSchoolName() {
    $name = 'Portadown Model Boys School';
    $location = 'Portadown, County Armagh';
    $date = $this->today();
    $reason = '';

    $expected = 'County Armagh';

    $closure = new SchoolClosure($name, $location, $date, $reason);
    $output = $closure->getData();

    $this->assertEquals($expected, $output['location']);
  }

  /**
   * Test reason matches one of the predefined text replacements.
   */
  public function testReasonMatchesPredefinedReplacement() {
    $name = 'All Saints Primary School';
    $location = 'Belfast';
    $date = $this->today();
    $reason = 'no water supply';

    $expected = 'due to no water supply.';

    $closure = new SchoolClosure($name, $location, $date, $reason);
    $output = $closure->getData();

    $this->assertEquals($expected, $output['reason']);
  }

  /**
   * Test Closure is expired if date is in the past.
   */
  public function testIsExpiredIfBeforeTodaysDate() {
    $name = 'All Saints Primary School';
    $location = 'Belfast';
    $date = date_sub($this->today(), date_interval_create_from_date_string("1 day"));
    $reason = 'no water supply';

    $expected = TRUE;

    $closure = new SchoolClosure($name, $location, $date, $reason);
    $output = $closure->isExpired();

    $this->assertEquals($expected, $output);
  }

  /**
   * Test Closure is not expired if date is in the future.
   */
  public function testIsNotExpiredIfAfterTodaysDate() {
    $name = 'All Saints Primary School';
    $location = 'Belfast';
    $date = date_add($this->today(), date_interval_create_from_date_string("1 day"));
    $reason = 'no water supply';

    $expected = FALSE;

    $closure = new SchoolClosure($name, $location, $date, $reason);
    $output = $closure->isExpired();

    $this->assertEquals($expected, $output);
  }

  /**
   * Test Closure is not expired if date is today.
   */
  public function testIsNotExpiredIfTodaysDate() {
    $name = 'All Saints Primary School';
    $location = 'Belfast';
    $date = $this->today();
    $reason = 'no water supply';

    $expected = FALSE;

    $closure = new SchoolClosure($name, $location, $date, $reason);
    $output = $closure->isExpired();

    $this->assertEquals($expected, $output);
  }

}

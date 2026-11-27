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
   * Location is used verbatim, as provided by the data source.
   */
  public function testLocation() {
    $name = 'All Saints Primary School';
    $location = 'Portadown, County Armagh';
    $date = $this->today();
    $reason = '';

    $expected = $location;

    $closure = new SchoolClosure($name, $location, $date, $reason);
    $output = $closure->getData();

    $this->assertEquals($expected, $output['location']);
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

  /**
   * Test closure is not expired if dateTo is in the future, even though
   * the start date is in the past.
   */
  public function testIsNotExpiredIfDateToIsInTheFuture() {
    $name = 'All Saints Primary School';
    $location = 'Belfast';
    $date = date_sub(clone $this->today(), date_interval_create_from_date_string('1 day'));
    $dateTo = date_add(clone $this->today(), date_interval_create_from_date_string('1 day'));
    $reason = 'no water supply';

    $expected = FALSE;

    $closure = new SchoolClosure($name, $location, $date, $reason, $dateTo);
    $output = $closure->isExpired();

    $this->assertEquals($expected, $output);
  }

  /**
   * Test closure is expired once dateTo has passed.
   */
  public function testIsExpiredIfDateToIsInThePast() {
    $name = 'All Saints Primary School';
    $location = 'Belfast';
    $date = date_sub(clone $this->today(), date_interval_create_from_date_string('3 days'));
    $dateTo = date_sub(clone $this->today(), date_interval_create_from_date_string('1 day'));
    $reason = 'no water supply';
    // Note: both $date and $dateTo clone from $this->today() independently,
    // since date_sub()/date_add() mutate \DateTime in place.

    $expected = TRUE;

    $closure = new SchoolClosure($name, $location, $date, $reason, $dateTo);
    $output = $closure->isExpired();

    $this->assertEquals($expected, $output);
  }

}

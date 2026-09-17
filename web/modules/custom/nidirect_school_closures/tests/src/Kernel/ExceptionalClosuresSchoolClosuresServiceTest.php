<?php

namespace Drupal\Tests\nidirect_school_closures\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * @coversDefaultClass Drupal\nidirect_school_closures\Service\ExceptionalClosuresSchoolClosuresService
 *
 * @group nidirect_school_closures
 * @group nidirect
 */
class ExceptionalClosuresSchoolClosuresServiceTest extends KernelTestBase {

  /**
   * School closure service.
   *
   * @var mixed
   */
  protected $closureService;

  /**
   * Set Strict config Schema status.
   *
   * @var bool
   */
  protected $strictConfigSchema;

  /**
   * Machine name of module to test.
   *
   * @var array
   */
  protected static $modules = [
    'nidirect_school_closures',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installConfig(['nidirect_school_closures']);
    $this->closureService = \Drupal::service('nidirect_school_closures.source.exceptional_closures');
  }

  /**
   * Decode a JSON fixture file.
   */
  private function decodeFixture(string $filename): array {
    return json_decode(file_get_contents(__DIR__ . '/data/' . $filename), TRUE);
  }

  /**
   * Test schools with current closures are returned, and one with only an
   * expired closure is not.
   */
  public function testReturnCorrectNumberofClosures() {
    $this->closureService->setResponseData($this->decodeFixture('closures.json'));
    $this->closureService->processData();
    $data = $this->closureService->getData();

    // Our Lady's Girls' College, Harmony Primary School Belfast, St
    // Example's Primary School, Two Closures School. St Trinian's High
    // School is excluded as its only closure is expired.
    $expected = 4;
    $output = count($data);

    $this->assertEquals($expected, $output);
  }

  /**
   * Test when no closures are in effect.
   */
  public function testNoClosures() {
    $this->closureService->setResponseData($this->decodeFixture('noclosures.json'));
    $this->closureService->processData();
    $data = $this->closureService->getData();

    $expected = 0;
    $output = count($data);

    $this->assertEquals($expected, $output);
  }

  /**
   * Test error state when valid data is present.
   */
  public function testErrorStateFalse() {
    $this->closureService->setResponseData($this->decodeFixture('closures.json'));
    $this->closureService->processData();

    $output = $this->closureService->hasErrors();

    $this->assertFalse($output);
  }

  /**
   * Test error state when malformed data is retrieved.
   */
  public function testErrorStateTrue() {
    $this->closureService->setResponseData($this->decodeFixture('blank.json'));
    $this->closureService->processData();

    $output = $this->closureService->hasErrors();

    $this->assertTrue($output);
  }

  /**
   * Find a school by name in a processed closures dataset.
   */
  private function findSchool(array $data, string $name): array {
    return current(array_filter($data, function ($item) use ($name) {
      return $item['name'] === $name;
    }));
  }

  /**
   * Test multiple reasons on a closure are combined into one sentence.
   */
  public function testMultipleReasonsAreCombined() {
    $this->closureService->setResponseData($this->decodeFixture('closures.json'));
    $this->closureService->processData();
    $data = $this->closureService->getData();

    $school = $this->findSchool($data, 'Harmony Primary School Belfast');

    $expected = 'due to adverse weather and use as a polling station for an election.';

    $this->assertEquals($expected, $school['closures'][0]['reason']);
  }

  /**
   * Test a closure's dateTo is captured when present.
   */
  public function testDateRangeIsCaptured() {
    $this->closureService->setResponseData($this->decodeFixture('closures.json'));
    $this->closureService->processData();
    $data = $this->closureService->getData();

    $school = $this->findSchool($data, "St Example's Primary School");

    $this->assertInstanceOf(\DateTime::class, $school['closures'][0]['dateTo']);
    $this->assertEquals('2030-10-03', $school['closures'][0]['dateTo']->format('Y-m-d'));
  }

  /**
   * Test a school with multiple current closures has all of them listed,
   * in date order, while an expired closure for the same school is
   * filtered out.
   */
  public function testAllCurrentClosuresListedForSchoolWithMultipleEntries() {
    $this->closureService->setResponseData($this->decodeFixture('closures.json'));
    $this->closureService->processData();
    $data = $this->closureService->getData();

    $school = $this->findSchool($data, 'Two Closures School');

    $this->assertCount(2, $school['closures']);
    $this->assertEquals('2031-01-05', $school['closures'][0]['date']->format('Y-m-d'));
    $this->assertEquals('2031-02-10', $school['closures'][1]['date']->format('Y-m-d'));
  }

}

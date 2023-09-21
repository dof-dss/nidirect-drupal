<?php

namespace Drupal\Tests\nidirect_school_closures\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * @coversDefaultClass Drupal\nidirect_school_closures\Service\C2kschoolsSchoolClosuresService
 *
 * @group nidirect_school_closures
 * @group nidirect
 */
class C2kschoolsSchoolClosuresServiceTest extends KernelTestBase {

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
  public static $modules = [
    'nidirect_school_closures',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installConfig(['nidirect_school_closures']);
    $this->closureService = \Drupal::service('nidirect_school_closures.source.cskschools');
  }

  /**
   * Test 2 current and 1 expired closure.
   */
  public function testReturnCorrectNumberofClosures() {
    $xml = simplexml_load_file(__DIR__ . '/data/closures.xml');

    $this->closureService->setXml($xml);
    $this->closureService->processData();
    $data = $this->closureService->getData();

    $expected = 2;
    $output = count($data);

    $this->assertEquals($expected, $output);
  }

  /**
   * Test when no closures are in effect.
   */
  public function testNoClosures() {
    $xml = simplexml_load_file(__DIR__ . '/data/noclosures.xml');

    $this->closureService->setXml($xml);
    $this->closureService->processData();
    $data = $this->closureService->getData();

    $expected = 0;
    $output = count($data);

    $this->assertEquals($expected, $output);
  }

  /**
   * Test error state when valid XML is present.
   */
  public function testErrorStateFalse() {
    $xml = simplexml_load_file(__DIR__ . '/data/closures.xml');
    $this->closureService->setXml($xml);
    $this->closureService->processData();

    $output = $this->closureService->hasErrors();

    $this->assertFalse($output);
  }

  /**
   * Test error state when invalid XML is retrived.
   */
  public function testErrorStateTrue() {
    $xml = simplexml_load_file(__DIR__ . '/data/blank.xml');
    $this->closureService->setXml($xml);
    $this->closureService->processData();

    $output = $this->closureService->hasErrors();

    $this->assertTrue($output);
  }

}

<?php

namespace Drupal\Tests\nidirect_cold_weather_payments\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;

/**
 * Cold Weather Payments tests.
 *
 * @group nidirect_cold_weather_payments
 * @group nidirect
 */
class ColdWeatherPaymentsTest extends EntityKernelTestBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Cold Weather Payments service.
   *
   * @var \Drupal\nidirect_cold_weather_payments\Service\ColdWeatherPaymentsService
   */
  protected $paymentsService;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'user',
    'system',
    'node',
    'field',
    'text',
    'datetime',
    'datetime_range',
    'filter',
    'entity_test',
    'nidirect_cold_weather_payments',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig('nidirect_cold_weather_payments');
    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->paymentsService = $this->container->get('nidirect_cold_weather_payments.payments');

    // Create and save two payment nodes so we can test the latest payment
    // period functionality.
    Node::create([
      'title' => t('CWP test 2019'),
      'type' => 'cold_weather_payment',
      'language' => 'en',
      'created' => 1575729507,
      'field_cwp_payments_period' => [
        'value' => "2019-12-01",
        'end_value' => "2020-01-31",
      ],
      'field_cwp_payments_triggered' => [
        [
          'date_start' => '2020-12-03',
          'date_end' => '2020-12-17',
          'stations' => 'magilligan',
        ],
      ],
    ])->save();

    Node::create([
      'title' => t('CWP test 2020'),
      'type' => 'cold_weather_payment',
      'language' => 'en',
      'created' => 1606918937,
      'field_cwp_payments_period' => [
        'value' => "2020-12-03",
        'end_value' => "2020-12-25",
      ],
      'field_cwp_payments_triggered' => [
        [
          'date_start' => '2020-12-03',
          'date_end' => '2020-12-17',
          'stations' => 'aldergrove,glenanne,magilligan',
        ],
        [
          'date_start' => '2020-12-10',
          'date_end' => '2020-12-22',
          'stations' => 'katesbridge',
        ],
        [
          'date_start' => '2020-12-22',
          'date_end' => '2020-12-28',
          'stations' => 'aldergrove',
        ],
      ],
    ])->save();
  }

  /**
   * Tests the current payment period.
   */
  public function testPaymentPeriod() {

    // Postcode that matches the 'Katesbridge' station.
    $postcode = 'BT24 1AB';

    $result = $this->paymentsService->forPostcode($postcode);

    self::assertNotEquals([
      'date_start' => '2019-12-01',
      'date_end' => '2020-01-31',
    ], $result['payments_period']);

    self::assertEquals([
      'date_start' => '2020-12-03',
      'date_end' => '2020-12-25',
    ], $result['payments_period']);
  }

  /**
   * Tests that payment details are returned for qualifying postcode.
   */
  public function testPostcodeHasPayment() {

    // Postcode that matches the 'Katesbridge' station.
    $postcode = 'BT24 1AB';

    $result = $this->paymentsService->forPostcode($postcode);

    self::assertEquals([
      'date_start' => '2020-12-10',
      'date_end' => '2020-12-22',
      'stations' => 'katesbridge',
      'postcodes' => ['24', '25', '26', '30', '31', '32', '33', '34'],
      'payment_granted' => TRUE,
    ], $result['payments_triggered'][1]);
  }

  /**
   * Tests that multiple payment details are returned for qualifying postcode.
   */
  public function testPostcodeHasMultiplePayments() {

    // Postcode that matches the 'Aldergrove' station.
    $postcode = 'BT28 1AB';

    $result = $this->paymentsService->forPostcode($postcode);

    self::assertEquals([
      'date_start' => '2020-12-03',
      'date_end' => '2020-12-17',
      'stations' => 'aldergrove,glenanne,magilligan',
      'postcodes' => [
        '27', '28', '29', '39', '40', '41', '42', '43', '44',
        '45', '46', '80', '35', '60', '61', '62', '63', '64',
        '65', '66', '67', '68', '69', '70', '71', '47', '48',
        '49', '51', '52', '53', '54', '55', '56', '57',
      ],
      'payment_granted' => TRUE,
    ], $result['payments_triggered'][0]);

    self::assertEquals([
      'date_start' => '2020-12-22',
      'date_end' => '2020-12-28',
      'stations' => 'aldergrove',
      'postcodes' => [
        '27', '28', '29', '39', '40', '41', '42', '43', '44',
        '45', '46', '80',
      ],
      'payment_granted' => TRUE,
    ], $result['payments_triggered'][2]);
  }

  /**
   * Tests that payment details are not returned for a postcode.
   */
  public function testPostcodeHasNoPayment() {

    // Postcode that matches the 'Stormont' station.
    $postcode = 'BT15 1AB';

    $result = $this->paymentsService->forPostcode($postcode);

    self::assertEquals([
      'date_start' => '2020-12-03',
      'date_end' => '2020-12-17',
      'stations' => 'aldergrove,glenanne,magilligan',
      'postcodes' => [
        '27', '28', '29', '39', '40', '41', '42', '43', '44',
        '45', '46', '80', '35', '60', '61', '62', '63', '64',
        '65', '66', '67', '68', '69', '70', '71', '47', '48',
        '49', '51', '52', '53', '54', '55', '56', '57',
      ],
      'payment_granted' => FALSE,
    ], $result['payments_triggered'][0]);

    self::assertEquals([
      'date_start' => '2020-12-10',
      'date_end' => '2020-12-22',
      'stations' => 'katesbridge',
      'postcodes' => [
        '24', '25', '26', '30', '31', '32', '33', '34',
      ],
      'payment_granted' => FALSE,
    ], $result['payments_triggered'][1]);

    self::assertEquals([
      'date_start' => '2020-12-22',
      'date_end' => '2020-12-28',
      'stations' => 'aldergrove',
      'postcodes' => [
        '27', '28', '29', '39', '40', '41', '42', '43', '44',
        '45', '46', '80',
      ],
      'payment_granted' => FALSE,
    ], $result['payments_triggered'][2]);
  }

}

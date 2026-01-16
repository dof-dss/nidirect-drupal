<?php

namespace Drupal\Tests\filelog\Functional;

use Drupal\Component\FileSecurity\FileSecurity;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\User;

/**
 * Test the filelog settings form.
 *
 * @group filelog
 */
class FileLogSettingsTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  protected static $modules = ['dblog', 'filelog'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user permitted to change site configuration.
   *
   * @var \Drupal\user\Entity\User
   */
  private User $adminUser;

  /**
   * {@inheritdoc}
   */
  public static function setUpBeforeClass(): void {
    parent::setUpBeforeClass();
    // Include the filelog.module file for @covers.
    require_once __DIR__ . '/../../../filelog.module';
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();

    // Check that the log directory and .htaccess are created on install.
    $logPath = $this->config('filelog.settings')->get('location');
    static::assertStringEqualsFile("$logPath/.htaccess", FileSecurity::htaccessLines(), '.htaccess file written correctly on install.');

    $this->adminUser = $this->createUser(['administer site configuration']);
  }

  /**
   * Test the settings form (part of the core logging settings).
   *
   * @param array $settings
   *   The settings values to save.
   * @param array $expected
   *   The expected values of the filelog.settings config object.
   * @param string $error
   *   If the form submission is invalid, the expected error message.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   *
   * @covers ::filelog_form_system_logging_settings_alter
   * @covers ::filelog_logging_settings_validate
   * @covers ::filelog_logging_settings_submit
   *
   * @dataProvider providerTestSettingsForm
   */
  public function testSettingsForm(array $settings, array $expected, string $error = NULL): void {
    $this->drupalLogin($this->adminUser);

    // Alter the filelog settings.
    $this->drupalGet('/admin/config/development/logging');
    $this->assertSession()->pageTextContains('Log messages to file');
    $this->submitForm($settings, 'Save configuration');

    if (!$error) {
      $this->assertSession()->pageTextContains('The configuration options have been saved.');

      // Check that the settings were saved.
      $config = $this->config('filelog.settings')->getRawData();
      unset($config['_core']);
      static::assertEquals($expected, $config, 'Configuration is saved as expected.');

      $logPath = $this->config('filelog.settings')->get('location');
      static::assertStringEqualsFile("$logPath/.htaccess", FileSecurity::htaccessLines(), '.htaccess file written correctly.');
    }
    else {
      $this->assertSession()->pageTextNotContains('The configuration options have been saved.');
      $this->assertSession()->pageTextContains($error);
    }
  }

  /**
   * Data provider for ::testSettingsForm.
   *
   * @return array
   *   An array of test cases.
   */
  public function providerTestSettingsForm(): array {
    $default_settings = [
      'enabled'  => TRUE,
      'location' => 'public://logs',
      'rotation' => [
        'schedule'    => 'daily',
        'delete'      => FALSE,
        'destination' => 'archive/[date:custom:Y/m/d].log',
        'gzip'        => TRUE,
      ],
      'format' => '[[log:created]] [[log:level]] [[log:channel]] [client: [log:ip], [log:user]] [log:message]',
      'level'  => 7,
      'channels_type' => 'exclude',
      'channels' => [],
    ];

    $test_cases = [];

    // Test multiple channels, with one per line.
    $case['settings']['filelog[channels]'] = "channel1\nchannel2\nchannel3";
    $case['expected'] = $default_settings;
    $case['expected']['channels'] = [
      'channel1',
      'channel2',
      'channel3',
    ];
    $test_cases[] = $case;

    $case['settings']['filelog[channels]'] = "channel1\rchannel2\nchannel3\r\nchannel4\n\n\rchannel5";
    $case['expected']['channels'] = [
      'channel1',
      'channel2',
      'channel3',
      'channel4',
      'channel5',
    ];
    $test_cases[] = $case;

    return $test_cases;
  }

}

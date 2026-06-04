<?php

declare(strict_types=1);

namespace Drupal\Tests\nidirect_common\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * @covers ::nidirect_common_obfuscate_emails
 */
class ObfuscateEmailsTest extends UnitTestCase {

  /**
   * Load the module code defining nidirect_common_obfuscate_emails().
   */
  protected function setUp(): void {
    parent::setUp();
    // Adjust path if your module lives somewhere else.
    require_once DRUPAL_ROOT . '/modules/custom/nidirect_common/nidirect_common.module';
  }

  /**
   * @dataProvider providerEmailObfuscation
   */
  public function testEmailObfuscation(string $input, string $expected): void {
    $this->assertSame($expected, nidirect_common_obfuscate_emails($input));
  }

  /**
   * Basic scenarios for obfuscation.
   *
   * @return array[]
   *   Test cases.
   */
  public function providerEmailObfuscation(): array {
    return [
      'simple email' => [
        'Contact john.smith@example.com for details.',
        'Contact j********h@example.com for details.',
      ],
      'display name with angle brackets' => [
        'smithj <john.smith@example.com>',
        'smithj <j********h@example.com>',
      ],
      'multiple emails in one string' => [
        'To: john@example.com, CC: alice@test.org',
        'To: j**n@example.com, CC: a***e@test.org',
      ],
      'no emails in string' => [
        'This string has no email address.',
        'This string has no email address.',
      ],
      'short local part length 1' => [
        'Email a@example.com now',
        'Email *@example.com now',
      ],
      'short local part length 2' => [
        'Use ab@example.com please',
        'Use **@example.com please',
      ],
      'plus tag in local part' => [
        'Account: user+tag@domain.com',
        'Account: u******g@domain.com',
      ],
      'dot in local part' => [
        'Primary: john.doe@domain.com',
        'Primary: j******e@domain.com',
      ],
      'email at beginning and end' => [
        'first@example.com and last@test.com',
        'f***t@example.com and l**t@test.com',
      ],
      'multiline string' => [
        "Line one: john@example.com\nLine two: jane@test.org",
        "Line one: j**n@example.com\nLine two: j**e@test.org",
      ],
    ];
  }

  /**
   * Ensure domain part is preserved.
   */
  public function testDomainIsPreserved(): void {
    $input = 'Contact john.doe@example.co.uk';
    $output = nidirect_common_obfuscate_emails($input);

    $this->assertStringContainsString('@example.co.uk', $output);
    $this->assertSame('Contact j******e@example.co.uk', $output);
  }

  /**
   * Obfuscation should be idempotent (safe to run multiple times).
   */
  public function testIdempotentObfuscation(): void {
    $input = 'smithj <john.smith@example.com>';

    $once = nidirect_common_obfuscate_emails($input);
    $twice = nidirect_common_obfuscate_emails($once);

    $this->assertSame('smithj <j********h@example.com>', $once);
    $this->assertSame($once, $twice, 'Obfuscation must be idempotent.');
  }

  /**
   * Already obfuscated local part should not get mangled further.
   */
  public function testAlreadyObfuscatedLocalPartIsNotChanged(): void {
    $input = 'Contact j******e@example.com for support.';
    $output = nidirect_common_obfuscate_emails($input);

    $this->assertSame(
      'Contact j******e@example.com for support.',
      $output
    );
  }

}

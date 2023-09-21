<?php

namespace Drupal\Tests\nidirect_money_advice_articles\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\nidirect_money_advice_articles\ArticleProcessors;

/**
 * @coversDefaultClass Drupal\nidirect_money_advice_articles\ArticleProcessors
 *
 * @group nidirect_money_advice_articles
 * @group nidirect
 */
class ArticleProcessorsTest extends KernelTestBase {

  /**
   * Set Strict config Schema status.
   *
   * @var bool
   */
  protected $strictConfigSchema;

  /**
   * Article processing methods.
   *
   * @var \Drupal\nidirect_money_advice_articles\ArticleProcessors
   */
  protected $articleProcessor;

  /**
   * Module machine name.
   *
   * @var array
   */
  public static $modules = [
    'nidirect_money_advice_articles',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installConfig(['nidirect_money_advice_articles']);
  }

  /**
   * Test article body with source summary.
   */
  public function testBodyWithSummary() {
    $xml = simplexml_load_file(__DIR__ . '/data/bodyWithSummary.xml');

    $description = $xml->xpath('//rss/channel/item/description');
    $output = ArticleProcessors::body($description[0]->__toString());

    $this->assertContains('<p>The first step to taking control of your finances is doing a budget.</p>', $output);
    $this->assertContains('<p>It will take a little effort, but it’s a great way to get a quick snapshot of the money you have coming in and going out.</p>', $output);
    $this->assertContains('<p>Setting up a budget means you’re:</p>', $output);
    $this->assertNotContains('Taking the time to manage your money better can really pay off.', $output);
  }

  /**
   * Test article body without source summary.
   */
  public function testBodyWithoutSummary() {
    $xml = simplexml_load_file(__DIR__ . '/data/bodyWithoutSummary.xml');

    $description = $xml->xpath('//rss/channel/item/description');
    $output = ArticleProcessors::body($description[0]->__toString());

    $this->assertContains('<p>The first step to taking control of your finances is doing a budget.</p>', $output);
    $this->assertContains('<p>It will take a little effort, but it’s a great way to get a quick snapshot of the money you have coming in and going out.</p>', $output);
    $this->assertContains('<p>Setting up a budget means you’re:</p>', $output);
    $this->assertNotContains('Taking the time to manage your money better can really pay off.', $output);
  }

  /**
   * Test article summary with source summary.
   */
  public function testSummaryWithSummary() {
    $xml = simplexml_load_file(__DIR__ . '/data/bodyWithSummary.xml');

    $description = $xml->xpath('//rss/channel/item/description');
    $output = ArticleProcessors::summary($description[0]->__toString());

    $this->assertContains('Taking the time to manage your money better can really pay off.', $output);
  }

  /**
   * Test article summary without source summary.
   */
  public function testSummaryWithoutSummary() {
    $xml = simplexml_load_file(__DIR__ . '/data/bodyWithoutSummary.xml');

    $description = $xml->xpath('//rss/channel/item/description');
    $output = ArticleProcessors::summary($description[0]->__toString());

    $this->assertNotContains('Taking the time to manage your money better can really pay off.', $output);
  }

  /**
   * Test article teaser with source summary.
   */
  public function testTeaserWithSummary() {
    $xml = simplexml_load_file(__DIR__ . '/data/bodyWithSummary.xml');

    $description = $xml->xpath('//rss/channel/item/description');
    $output = ArticleProcessors::teaser($description[0]->__toString());

    $this->assertContains('Taking the time to manage your money better can really pay off. It can help you stay on top of your bills and save...', $output);
  }

  /**
   * Test article teaser without source summary.
   */
  public function testTeaserWithoutSummary() {
    $xml = simplexml_load_file(__DIR__ . '/data/bodyWithoutSummary.xml');

    $description = $xml->xpath('//rss/channel/item/description');
    $output = ArticleProcessors::teaser($description[0]->__toString());

    $this->assertContains('Advice on managing your money from the Money Advice Service', $output);
  }

}

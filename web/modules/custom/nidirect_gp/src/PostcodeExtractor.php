<?php

namespace Drupal\nidirect_gp;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class PostcodeExtractor.
 *
 * Takes a regex from the container parameters and matches
 * text strings against it.
 */
class PostcodeExtractor {

  /**
   * Postcode regex to match strings against.
   *
   * @var string
   */
  protected $postcodeRegex;

  /**
   * PostcodeExtractor constructor.
   *
   * @param string $postcode_regex
   *   Postcode regex; sourced from the container parameters.
   */
  public function __construct(string $postcode_regex) {
    $this->postcodeRegex = $postcode_regex;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('postcode_matches.uk')
    );
  }

  /**
   * Reviews a string to match against a pre-defined UK postcode regex.
   *
   * @param string $text_to_match
   *   Text to check for postcode matches.
   *
   * @return string
   *   Matching text values.
   */
  public function getPostCode(string $text_to_match) {
    $matches = [];
    if (preg_match_all('/' . $this->postcodeRegex . '/i', $text_to_match, $matches)) {
      return $matches[0];
    }
    return NULL;
  }

}

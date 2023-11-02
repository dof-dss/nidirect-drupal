<?php

namespace Drupal\nidirect_money_advice_articles;

/**
 * Callback functions for article migrations.
 */
class ArticleProcessors {

  /**
   * Removes summary text from body value.
   */
  public static function body($value) {
    $pattern = '/<p><strong>.*?<\/strong><\/p>(.*?)$/s';
    if (preg_match($pattern, $value, $matches)) {
      return $matches[1];
    }

    return $value;
  }

  /**
   * Extracts summary text from body value.
   */
  public static function summary($value) {
    $pattern = '/<p><strong>(.*?)<\/strong>/s';
    if (preg_match($pattern, $value, $matches)) {
      return $matches[1];
    }

    return $value;
  }

  /**
   * Extracts teaser text from body value.
   */
  public static function teaser($value) {
    $pattern = '/<p><strong>(.*?)<\/strong>/s';
    if (preg_match($pattern, $value, $matches)) {
      $summary = $matches[1];
      $teaser = substr($summary, 0, strrpos(substr($summary, 0, 120), ' ')) . '...';
    }
    else {
      $teaser = 'Advice on managing your money from the Money Advice Service';
    }
    return $teaser;
  }

  /**
   * Return the term id for theme/subtheme.
   */
  public static function subtheme($value) {
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
      'vid' => 'site_themes',
      'name' => 'Managing money',
    ]);

    if (is_array($terms)) {
      $term = reset($terms);
      return $term->id();
    }
  }

}

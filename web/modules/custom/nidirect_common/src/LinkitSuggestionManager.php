<?php

namespace Drupal\nidirect_common;

/**
 * @file
 * Contains \Drupal\nidirect_common\LinkitSuggestionManager.
 */

use Drupal\Component\Utility\Html;
use Drupal\Core\Url;
use Drupal\linkit\ProfileInterface;
use Drupal\linkit\Suggestion\DescriptionSuggestion;
use Drupal\linkit\Suggestion\SuggestionCollection;
use Drupal\linkit\SuggestionManager;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Replacement suggestion service to handle Linkit autocomplete results.
 */
class LinkitSuggestionManager extends SuggestionManager {

  /**
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * LinkitSuggestionManager constructor.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request
   *   The requeststack service.
   */
  public function __construct(RequestStack $request) {
    $this->requestStack = $request;
  }

  /**
   * {@inheritdoc}
   */
  public function getSuggestions(ProfileInterface $linkitProfile, $search_string) {

    $suggestions = new SuggestionCollection();

    // Display for empty searches.
    if (empty(trim($search_string))) {
      $suggestion = new DescriptionSuggestion();
      $suggestion->setLabel($this->t('No content results'));
      $suggestion->setDescription($this->t('Please enter search terms'));

      $suggestions->addSuggestion($suggestion);
    }

    // Special for link to front page.
    if (strpos($search_string, 'front') !== FALSE) {
      $suggestion = new DescriptionSuggestion();
      $suggestion->setGroup($this->t('System'));
      $suggestion->setLabel($this->t('Front page'));
      $suggestion->setDescription($this->t('The front page for this site'));
      $suggestion->setPath(Url::fromRoute('<front>')->toString());

      $suggestions->addSuggestion($suggestion);
    }

    // Check for an e-mail address and return as mail-to link if appropriate.
    if (filter_var($search_string, FILTER_VALIDATE_EMAIL)) {
      $suggestion = new DescriptionSuggestion();
      $suggestion->setGroup($this->t('E-mail'));
      $suggestion->setLabel($this->t('E-mail @email', ['@email' => $search_string]));
      $suggestion->setDescription($this->t('Creates a mailto link for e-mail @email', ['@email' => $search_string]));
      $suggestion->setPath('mailto:' . Html::escape($search_string));

      $suggestions->addSuggestion($suggestion);
    }

    // Prune any absolute URLs matching this site so we can match to internal content.
    if ($this->isAbsoluteUrlForThisSite($search_string)) {
      $url_parts = parse_url($search_string);
      $search_string = $url_parts['path'] . $url_parts['query'];
    }

    // Perform the standard search.
    foreach ($linkitProfile->getMatchers() as $plugin) {
      $suggestions->addSuggestions($plugin->execute($search_string));
    }

    return $suggestions;
  }

  /**
   * Function to assess whether a search string is
   * referencing an environment used by this site.
   *
   * @param string $search_string
   *   The search string.
   * @return bool
   *   True if for this site, False if not.
   */
  protected function isAbsoluteUrlForThisSite(string $search_string) {
    $host = $this->requestStack->getCurrentRequest()->getHost();
    return preg_match("|(${host}\/)|", $search_string);
  }

}

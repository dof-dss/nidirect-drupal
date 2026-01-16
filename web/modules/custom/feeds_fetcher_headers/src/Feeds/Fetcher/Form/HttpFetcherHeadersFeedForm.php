<?php

namespace Drupal\feeds_fetcher_headers\Feeds\Fetcher\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\feeds\FeedInterface;
use Drupal\feeds\Feeds\Fetcher\Form\HttpFetcherFeedForm;

/**
 * Provides a form on the feed edit page for the HttpFetcher.
 */
class HttpFetcherHeadersFeedForm extends HttpFetcherFeedForm {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, FeedInterface $feed = NULL) {
    parent::buildConfigurationForm($form, $form_state, $feed);

    $form = parent::buildConfigurationForm($form, $form_state, $feed);

    $form['headers'] = [
      '#title' => $this->t('Headers:'),
      '#description' => $this->t("List of Header pair values"),
      '#type' => 'textarea',
      '#default_value' => $feed->getConfigurationFor($this->plugin)['headers'],
    ];
    $form['httpmethod'] = [
      '#title' => $this->t('HTTP Method'),
      '#type' => 'select',
      '#options' => [
        'GET' => 'GET',
        'POST' => 'POST',
      ],
      '#default_value' => $feed->getConfigurationFor($this->plugin)['httpmethod'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state, FeedInterface $feed = NULL) {
    parent::submitConfigurationForm($form, $form_state, $feed);
    $feed_config = $feed->getConfigurationFor($this->plugin);
    $feed_config['headers'] = $form_state->getValue('headers');
    $feed_config['httpmethod'] = $form_state->getValue('httpmethod');
    $feed->setConfigurationFor($this->plugin, $feed_config);
  }

}

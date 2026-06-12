<?php

namespace Drupal\nidirect_cold_weather_payments\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Renderer;
use Drupal\nidirect_cold_weather_payments\Service\ColdWeatherPaymentsService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for checking cold weather payments.
 */
class ColdWeatherPaymentCheckerForm extends FormBase {

  /**
   * Drupal\Core\Render\Renderer definition.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * Cold Weather Payments service.
   *
   * @var \Drupal\nidirect_cold_weather_payments\Service\ColdWeatherPaymentsService
   */
  protected $coldWeatherPaymentsService;

  /**
   * Constructs a new ColdWeatherPaymentCheckerForm object.
   */
  public function __construct(
    Renderer $renderer,
    ColdWeatherPaymentsService $cold_weather_payments_service
  ) {
    $this->renderer = $renderer;
    $this->coldWeatherPaymentsService = $cold_weather_payments_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer'),
      $container->get('nidirect_cold_weather_payments.payments')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cold_weather_payment_checker_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['#attributes'] = [
      'role' => 'search',
      'class' => ['search-form', 'search-form--cwp'],
    ];

    $form['postcode'] = [
      '#type' => 'textfield',
      '#maxlength' => 8,
      '#size' => 8,
      '#weight' => '0',
      '#title' => t('Enter your Northern Ireland postcode'),
      '#title_display' => 'before',
      '#attributes' => [
        'autocomplete' => 'postal-code',
      ],
      '#default_value' => $form_state->getValue('postcode', ''),
      '#ajax' => [
        'callback' => '::clearErrors',
        'event' => 'focus',
        'progress' => ['type' => 'none'],
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Check postcode'),
      '#attributes' => [
        'class' => ['form-submit'],
      ],
      '#ajax' => [
        'callback' => '::submitAjax',
        'wrapper' => 'cwp-results-container',
        'effect' => 'fade',
        'method' => 'replace',
      ],
    ];

    // Placeholder for postcode validation error messages.
    $form['postcode-error-message'] = [
      '#type' => 'container',
      '#attributes' => [
        'role' => 'alert',
        'class' => ['cwp-error-container'],
      ],
    ];

    // Placeholder to put the results in.
    $form['message'] = [
      '#type' => 'container',
      '#attributes' => [
        'role' => 'alert',
        'class' => ['cwp-results-container'],
      ],
      'content' => [
        '#markup' => $form_state->get('message'),
        '#prefix' => '<div id="cwp-results">',
        '#suffix' => '</div>',
      ],
    ];

    return $form;
  }

  /**
   * Validates that a postcode is a valid NI postcode or outward code.
   *
   * Validates BT area and outward part (first half of the postcode).
   */
  protected function isValidNiPostcode(array &$form, FormStateInterface $form_state) {
    return preg_match('/^BT[0-9]{1,2}( ?[0-9][A-Z]{2})?$/i', $form_state->getValue('postcode'));
  }

  /**
   * AJAX callback to process form submission.
   */
  public function submitAjax(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // Set error message if postcode does not validate.
    if (!$this->isValidNiPostcode($form, $form_state)) {
      $content = '<strong class="error form-item--error-message">' . t('Postcode must be a valid Northern Ireland postcode.') . '</strong>';
      $response->addCommand(
        new HtmlCommand('#edit-postcode-error-message', $content)
      );
      $response->addCommand(
        new InvokeCommand('#edit-postcode', 'addClass', ['error'])
      );

      return $response;
    }

    // At this stage we have a valid NI postcode - get the postcode district
    // and look it up for CWP payments.
    $postcode = $form_state->getValue('postcode');
    $postcode_district = $this->cwpGetPostcodeDistrict($postcode);
    $data = $this->cwpLookup($postcode_district);

    $output = $this->resultsRender($data);

    $response->addCommand(
      new HtmlCommand('#cwp-results', $output)
    );

    return $response;
  }

  /**
   * AJAX callback to clear errors.
   */
  public function clearErrors() {

    $response = new AjaxResponse();

    // Remove any errors set in previous ajax callbacks.
    $response->addCommand(
      new InvokeCommand('#edit-postcode', 'removeClass', ['error'])
    );
    $response->addCommand(
      new RemoveCommand('#edit-postcode-error-message .error')
    );

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Since validation is handled by ajax callback, in the unlikely event that
    // JS is disabled, validation will not have been performed. So do some basic
    // validation here before processing the form submission.
    $error = [
      '#prefix' => '<p class="info-notice info-notice--error">',
      '#markup' => $this->t('An error has occurred.'),
      '#suffix' => '</p>',
    ];

    if (!$this->isValidNiPostcode($form, $form_state)) {
      $error['#markup'] = $this->t('Postcode must be a valid Northern Ireland postcode');
      $output = $this->renderer->render($error);
    }
    else {
      $postcode = $form_state->getValue('postcode');
      $postcode_district = $this->cwpGetPostcodeDistrict($postcode);
      $data = $this->cwpLookup($postcode_district);

      $output = $this->resultsRender($data);
    }

    $form_state->set('message', $output);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Fetch CWP data and process payment triggers.
   */
  private function cwpLookup($postcode_district) {
    try {
      $data = $this->coldWeatherPaymentsService->forPostcode('BT' . $postcode_district);

      $payments = [];
      if (array_key_exists('payments_triggered', $data)) {
        foreach ($data['payments_triggered'] as $trigger) {
          if ($trigger['payment_granted']) {
            $payments[] = [
              'date_start' => $trigger['date_start'],
              'date_end' => $trigger['date_end'],
            ];
          }
        }
      }
      $data['payments'] = $payments;

      return $data;
    }
    catch (\Exception $e) {
      \Drupal::logger('nidirect_cold_weather_payments')->error($e->getMessage());
      return ['has_error' => TRUE];
    }
  }

  /**
   * Get postcode district from a Northern Ireland postcode.
   *
   * For Northern Ireland postcodes, the postcode district is always 1 or 2
   * digits following the postcode area 'BT'.
   */
  private function cwpGetPostcodeDistrict(string $postcode) {
    $postcode = trim($postcode);
    $postcode_district = NULL;

    // If postcode is a full NI postcode, or just the first part
    // (outward code - e.g. BT1) ...
    if (strlen($postcode) > 4) {
      // Full postcode - remove first 2 and last 3 characters plus any
      // trailing spaces to get the district number.
      $postcode_district = rtrim(substr($postcode, 2, -3));
    }
    else {
      // Postcode outward code - just strip of first two 'BT' characters.
      $postcode_district = substr($postcode, 2);
    }

    return $postcode_district;
  }

  /**
   * Provides formatted rendered output for CWP results.
   */
  private function resultsRender($data) {
    // If the data results contains an error wrap in error element, otherwise
    // return a cwp result render array.
    if (is_null($data) || isset($data['has_error'])) {
      $output['#markup'] = $this->t('Sorry, there was a problem checking for Cold Weather Payments.');
      $output['#prefix'] = '<p class="info-notice info-notice--error">';
      $output['#suffix'] = '</p>';
    }
    else {
      if (isset($data['published_content']) && $data['published_content'] === 'none') {
        $output = [
          '#markup' => t('There are currently no published Cold Weather Payments'),
        ];
      }
      else {
        $output = [
          '#theme' => 'cwp_search_result',
          '#postcode' => $data['postcode'],
          '#period_start' => $data['payments_period']['date_start'],
          '#period_end' => $data['payments_period']['date_end'],
          '#payments' => $data['payments'],
        ];
      }
    }

    return $this->renderer->render($output);
  }

}

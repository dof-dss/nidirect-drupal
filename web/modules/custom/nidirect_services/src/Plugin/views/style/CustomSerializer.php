<?php

namespace Drupal\nidirect_services\Plugin\views\style;

use Drupal\rest\Plugin\views\style\Serializer;

/**
 * Custom serializer for view generating services-catalogue.json.
 * Ensure relative URLs in content are converted to absolute URLs.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "custom_serializer",
 *   title = @Translation("Custom Serializer"),
 *   help = @Translation("Serializes views row data using the Serializer component."),
 *   display_types = {"data"}
 * )
 */
class CustomSerializer extends Serializer {

  /**
   * {@inheritdoc}
   */
  public function render() {
    $host = \Drupal::request()->getSchemeAndHttpHost();

    // Pattern to match and replace relative paths found in fields
    // containing HTML.
    $html_pattern = '/(?:src|href)=[\'"]\K\/(?!\/)[^\'"]*/';

    // Pattern to match and replace relative paths in the app_url
    // field specifically.
    $app_url_pattern = '/^\/(?!\/).*$/';

    $rows = [];
    foreach ($this->view->result as $row_index => $row) {
      $this->view->row_index = $row_index;
      $row_render = $this->view->rowPlugin->render($row);
      $row_render = preg_replace($html_pattern, "$host$0", $row_render);
      if (isset($row_render['app_url'])) {
        $row_render['app_url'] = preg_replace($app_url_pattern, "$host$0", $row_render['app_url']);
      }
      $rows[] = $row_render;
    }
    unset($this->view->row_index);

    // Get the content type configured in the display or fallback to the
    // default.
    if ((empty($this->view->live_preview)) && method_exists($this->displayHandler, 'getContentType')) {
      $content_type = $this->displayHandler->getContentType();
    }
    else {
      $content_type = !empty($this->options['formats']) ? reset($this->options['formats']) : 'json';
    }
    return $this->serializer->serialize($rows, $content_type, ['views_style_plugin' => $this]);
  }

}

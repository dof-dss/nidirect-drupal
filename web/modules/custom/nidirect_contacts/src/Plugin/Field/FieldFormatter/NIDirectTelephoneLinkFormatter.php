<?php

namespace Drupal\nidirect_contacts\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\telephone_plus\Plugin\Field\FieldFormatter\TelephonePlusFieldFormatter;

/**
 * Plugin extending the 'telephone_plus_link' formatter.
 *
 * This plugin calls the parent viewElements method of the telephone_plus module
 * and checks for the existence of numbers that require reformatting of the
 * output from the libphonenumber-for-php library used in that module.
 *
 * @FieldFormatter(
 *   id = "nidirect_telephone_link",
 *   label = @Translation("NIDirect telephone link"),
 *   description = @Translation("Extends the TelephonePlus Link formatter with some custom output formatting."),
 *   field_types = {
 *     "telephone_plus_field"
 *   }
 * )
 */
class NIDirectTelephoneLinkFormatter extends TelephonePlusFieldFormatter {

  /**
   * {@inheritdoc}
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    // Array of characters to be removed from the number formatting to allow for
    // value comparisons.
    $formatting_chars = [' ', '+'];

    foreach ($items as $item) {

      $telephone_value = $item->get("telephone_number")->getString() ?? '';
      $unformatted_field_number = str_replace($formatting_chars, '', $telephone_value);
      // Match international and textphone numbers to replace the default
      // formatting provided by the libphonenumber library.
      if (strpos($unformatted_field_number, '00800') === 0 || strpos($unformatted_field_number, '18001') === 0) {
        // Iterate each render element to find the number to update.
        foreach ($elements as &$element) {
          // Does the current render element contain the unformatted number.
          if (array_key_exists('#number', $element) && !empty($element['#number']['#value'])) {
            if (is_array($element['number']['#value'])) {
              continue;
            }

            if (strpos($unformatted_field_number, str_replace($formatting_chars, '', $element['number']['#value'])) !== FALSE) {
              // Check the source value contains the prefix as some contacts use
              // the same number for phone and text phone.
              if (strpos($element['#number']['#value'], '00800') === 0 || strpos($element['#number']['#value'], '18001') === 0) {
                $element['#number']['#value'] = $telephone_value;
              }
            }
          }
        }
      }
      // Match numbers with extensions to replace the default
      // extension prefix provided by the libphonenumber library.
      $telephone_extension = $item->get("telephone_extension")->getString() ?? '';
      if ($telephone_extension) {
        // Iterate each render element to find the number to update.
        foreach ($elements as &$element) {
          // Does the current render element contain the telephone extension.
          if (array_key_exists('#extension', $element) && $element['#extension'] === $telephone_extension) {
            $element['#number']['#value'] = $telephone_value . ' ext. ' . $telephone_extension;
          }
          if (!empty($element['number']['#suffix']) && str_contains($element['number']['#suffix'], 'extension')) {
            unset($element['number']['#suffix']);
          }
        }
      }
    }

    return $elements;
  }

}

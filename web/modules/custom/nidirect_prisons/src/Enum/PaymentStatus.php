<?php

/**
 * @file
 * Defines the PaymentStatus enum for valid Worldpay payment statuses.
 */

namespace Drupal\nidirect_prisons\Enum;

/**
 * Worldpay order notification payment statuses.
 *
 * See https://docs.worldpay.com/apis/wpg/manage for a full list of
 * possible payment notification statuses. The actual statuses that
 * order notifications will contain is configurable in the Worldpay
 * Merchant Administration Interface (MAI).
 */
enum PaymentStatus: string {
  case AUTHORISED = 'AUTHORISED';
  case CANCELLED = 'CANCELLED';
  case SHOPPER_CANCELLED = 'SHOPPER_CANCELLED';
  case REFUSED = 'REFUSED';
  case ERROR = 'ERROR';

  /**
   * Returns a list of allowed statuses.
   */
  public static function allowed(): array {
    return [
      self::AUTHORISED,
      self::CANCELLED,
      self::SHOPPER_CANCELLED,
      self::REFUSED,
      self::ERROR,
    ];
  }

  /**
   * Helper function to check if a payment status is allowed.
   *
   * @param string $status
   *   The status to check.
   * @return bool
   *   Returns TRUE if $status is allowed, otherwise FALSE.
   */
  public static function isAllowed(string $status): bool {
    $enum = self::tryFrom($status);
    return $enum !== null && in_array($enum, self::allowed(), TRUE);
  }
}

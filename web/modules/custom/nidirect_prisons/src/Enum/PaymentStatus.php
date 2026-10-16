<?php

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
  case Authorised = 'AUTHORISED';
  case Cancelled = 'CANCELLED';
  case ShoppedCancelled = 'SHOPPER_CANCELLED';
  case Refused = 'REFUSED';
  case Error = 'ERROR';

  /**
   * Returns a list of allowed statuses.
   */
  public static function allowed(): array {
    return [
      self::Authorised,
      self::Cancelled,
      self::ShoppedCancelled,
      self::Refused,
      self::Error,
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
    return $enum !== NULL && in_array($enum, self::allowed(), TRUE);
  }

}

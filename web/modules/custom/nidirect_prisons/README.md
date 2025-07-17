# NIDirect Prisons

Custom module providing integration with NIPS (Northern Ireland Prison
Service) Prism system.

## Prisoner Payments Webform

`config/install/webform.webform.prisoner_payments.yml`

The webform allows nominated visitors (up to 3 per prisoner) to make
payments to prisoners. A weekly limit of £100 applies per prisoner.
The backend system (Prism) provides data on nominated visitors and
remaining payment allowances. REST API endpoints receive json data
from the NIPS Prism system regularly.

### Webform Flow

See:
`src/Plugin/WebformHandler/PrisonerPaymentsWebformHandler.php`
`src/Controller/WorldpayNotificationController.php`

1. Preamble (fraud warning, terms and conditions, etc)
2. Visitor and Prisoner details
   * User enters visitor name, visitor ID, visitor email and prisoner name, prisoner ID.
   * Visitor and prisoner ids are validated by the webform handler against stored details of nominated visitor ids who can make payments to a prisoner id (`table prisoner_payment_nominees`).
3. Payment amount:
   * Webform displays maximum amount that can be paid to the prisoner (by checking `table prisoner_payment_amount`)
   * User enters the payment amount, which is validated to check it does not exceed the maximum.
4. Payment card details ([Worldpay Hosted Payment Page – HPP](https://docs.worldpay.com/apis/wpg)):
   * HPP is embedded into an iframe via [Worldpay Javacript SDK](https://docs.worldpay.com/apis/wpg/hostedintegration/javascriptsdk)
   * User submits debit card details via the HPP
   * Worldpay processes and returns a response via a JS callback
   * If the response status indicates the payment is refused or some other error occurs, the user can try again.
   * If the response status indicates the payment is authorised, the JS callback stores the response in a hidden input and submits the webform.
   * Worldpay sends a (serverside) [payment status notification](https://docs.worldpay.com/apis/wpg/manage) to `WorldpayNotificationController.php`
   * `WorldpayNotificationController.php` handles updating the `prisoner_payment_transactions` and `prisoner_payment_amount` tables and sends an email to Prism with data on the payment transaction to enable Prism to update prisoner account balances.
5. Confirmation:
   * JS callback response is [verified to check integrity](https://docs.worldpay.com/apis/wpg/hostedintegration/securingpayments).
   * User is shown a success message
   * User receives [email notification from Worldpay](http://support.worldpay.com/support/kb/gg/merchantadmininterface/Merchant%20Interface%20Guide.htm#7integration/merchant_channel.htm) confirming the payment.


## Environment variables

### Worldpay service URL and authentication

The following environment variables must be set to enable integration
with Worldpay (see https://docs.worldpay.com/apis/wpg/hostedintegration/quickstart#setup):

* `PRISONER_PAYMENTS_WP_SERVICE_URL`
* `PRISONER_PAYMENTS_WP_USERNAME` (sensitive)
* `PRISONER_PAYMENTS_WP_PASSWORD` (sensitive)

### Payment requests

To make a payment request to Worldpay, the request must include the correct *merchant code* (see https://docs.worldpay.com/apis/wpg/hostedintegration/paymentrequests). There are separate merchant codes for each prison. The following environment variables must be configured:

* `PRISONER_PAYMENTS_WP_MERCHANT_CODE_MY` for HMP Maghaberry
* `PRISONER_PAYMENTS_WP_MERCHANT_CODE_MN` for HMP Magilligan
* `PRISONER_PAYMENTS_WP_MERCHANT_CODE_HW` for HMP Hydebank Wood

### Securing payments

See [https://docs.worldpay.com/apis/wpg/hostedintegration/securingpayments](https://docs.worldpay.com/apis/wpg/hostedintegration/securingpayments)

The following environment variable is required for verifying the MAC digital signature included in responses returned via the Worldpay Javascript SDK:

* `PRISONER_PAYMENTS_WP_MAC_SECRET` (sensitive)

### Prism email

`WorldpayNotificationController.php` sends email to Prism when notification of an AUTHORISED payment is received from Worldpay. The following environment variable must be set with the appropriate email address:

`PRISONER_PAYMENTS_PRISM_EMAIL`

NOTE the email address will be different for production and non-production environments.

## Prisoner Payment REST API endpoints

`POST /api/v1/prisoner-payments/nominees`
receives JSON data on prisoner IDs and associated visitor IDs allowed
to make payments to a prisoner.

`POST /api/v1/prisoner-payments/amounts`
receives JSON data on prisoner IDs and the maximum amount that can
be paid to a prisoner at any time.

`POST /api/v1/prisoner-payments/service-status`
receives JSON data on Prism prisoner payment service status. When
Prism is not available, the prisoner_payments webform is closed to anonymous
users.

### REST API Authentication

A user named `nidirect_prisons_api_user` with role `authenticated user`
must be created to act as a service account for accessing the
endpoints.

Ensure the authenticated user role has these permissions:
* *Access POST on Prisoner Payments Amounts Resource resource*
* *Access POST on Prisoner Payments Nominees Resource resource*
* *Access POST on Prisoner Payments Service Status Resource resource*

For clients to be authenticated as nidirect_prisons_api_user, they must
have a permitted IP address and must pass a valid authentication token
via the `X-Auth-Token` header in POST requests to the endpoints.

### Permitted IP addresses

Permitted IP addresses should be declared in an environment variable
`PRISONS_API_PERMITTED_IPS`. Multiple IP addresses should be comma
separated.

### Permitted authentication tokens

Permitted authentication tokens should be declared in environment
variable `PRISONS_API_PERMITTED_TOKENS`. Multiple token values should
be comma separated.

Tokens can be generated like so:

```
drush eval "echo Drupal\Component\Utility\Crypt::randomBytesBase64(74) . PHP_EOL"
```

**Generated tokens should always be stored and shared securely.**

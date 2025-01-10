NIDirect Prisons
---------------

Custom module providing REST API endpoints to receive POST json data
from the NIPS Prism system.

`POST /api/v1/prisoner-payments/nominees`
receives JSON data on prisoner IDs and associated visitor IDs allowed
to make payments to a prisoner.

`POST /api/v1/prisoner-payments/amounts`
receives JSON data on prisoner IDs and the maximum amount that can
be paid to a prisoner at any time.

### Authentication

A user named `nidirect_prisons_api_user` with role `authenticated user`
must be created to act as a service account for accessing the
endpoints.

Ensure the authenticated user role has these permissions:
* *Access POST on Prisoner Payments Amounts Resource resource*
* *Access POST on Prisoner Payments Nominees Resource resource*

For clients to be authenticated as nidirect_prisons_api_user, they must
have a permitted IP address and must pass a valid authentication token
via the `X-Auth-Token` header in POST requests to the endpoints.

#### Permitted IP addresses

Permitted IP addresses should be declared in an environment variable
`PRISONS_API_PERMITTED_IPS`. Multiple IP addresses should be comma
separated.

#### Permitted authentication tokens

Permitted authentication tokens should be declared in environment
variable `PRISONS_API_PERMITTED_TOKENS`. Multiple token values should
be comma separated.

Tokens can be generated like so:

```
drush eval "echo Drupal\Component\Utility\Crypt::randomBytesBase64(74) . PHP_EOL"
```

**Generated tokens should always be stored and shared securely.**

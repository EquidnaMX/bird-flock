# Routes Documentation

Bird Flock is a package and registers routes from `routes/web.php` through `Equidna\BirdFlock\BirdFlockServiceProvider`.

## How Routes Are Registered

- Service provider boot method calls `loadRoutesFrom(__DIR__ . '/../routes/web.php')`.
- Package route prefix in `routes/web.php`: `bird-flock`.

Health route registration is conditional:

- Controlled by `config('bird-flock.health.enabled', true)`.

## Route Inventory

### Health Routes

| Method | URI                                   | Name                         | Controller                              | Middleware |
| ------ | ------------------------------------- | ---------------------------- | --------------------------------------- | ---------- |
| GET    | `/bird-flock/health`                  | `bird-flock.health`          | `HealthCheckController@check`           | none       |
| GET    | `/bird-flock/health/circuit-breakers` | `bird-flock.health.circuits` | `HealthCheckController@circuitBreakers` | none       |

### Webhook Routes

All webhook routes are nested under middleware `throttle:60,1`.

| Method | URI                                            | Name                         | Controller                                | Middleware      |
| ------ | ---------------------------------------------- | ---------------------------- | ----------------------------------------- | --------------- |
| POST   | `/bird-flock/webhooks/twilio/status`           | `bird-flock.twilio.status`   | `TwilioWebhookController@status`          | `throttle:60,1` |
| POST   | `/bird-flock/webhooks/twilio/inbound`          | `bird-flock.twilio.inbound`  | `TwilioWebhookController@inbound`         | `throttle:60,1` |
| POST   | `/bird-flock/webhooks/sendgrid/events`         | `bird-flock.sendgrid.events` | `SendgridWebhookController@events`        | `throttle:60,1` |
| POST   | `/bird-flock/webhooks/vonage/delivery-receipt` | `bird-flock.vonage.dlr`      | `VonageWebhookController@deliveryReceipt` | `throttle:60,1` |
| POST   | `/bird-flock/webhooks/vonage/inbound`          | `bird-flock.vonage.inbound`  | `VonageWebhookController@inbound`         | `throttle:60,1` |
| POST   | `/bird-flock/webhooks/mailgun/events`          | `bird-flock.mailgun.events`  | `MailgunWebhookController@events`         | `throttle:60,1` |

## Package Routing Notes

- Route file location: `routes/web.php`.
- Health routes are disabled when `BIRD_FLOCK_HEALTH_ENABLED=false`.
- There is no package `routes/api.php`.
- Webhooks rely on signature validation in controllers, not Laravel auth guards.

## Host Application Expectations

- Host app must expose these URLs publicly (typically HTTPS) for provider callbacks.
- Host app should include any additional perimeter controls needed (WAF, reverse proxy restrictions).
- Host app can generate route URLs with Laravel `route()` helper if these names are available in the same route namespace.

## Assumptions

- The built-in `throttle:60,1` middleware is intentionally static in route definitions.
- `bird-flock.webhook_rate_limit` config exists but the custom middleware `RateLimitWebhooks` is not currently attached in package route definitions.

# API Documentation

Bird Flock does not provide public message-creation REST endpoints. Message creation is done programmatically (`BirdFlock::dispatch*`).

HTTP endpoints in this package are:
- Health endpoints.
- Provider webhook endpoints.

Base prefix: `/bird-flock`.

## Health Endpoints

### GET /bird-flock/health

- Name: `bird-flock.health`
- Controller: `HealthCheckController::check`
- Auth: none
- Middleware: none

Responses:
- `200` when `status=healthy`
- `503` when `status=degraded`

### GET /bird-flock/health/circuit-breakers

- Name: `bird-flock.health.circuits`
- Controller: `HealthCheckController::circuitBreakers`
- Auth: none
- Middleware: none
- Response: `200`

## Webhook Endpoints

Shared behavior:
- Provider-specific HTTP method (`GET` for LabsMobile ACK, `POST` for the other webhooks)
- middleware `throttle:60,1`
- success body is plain `OK`
- signature/authorization checks handled in controllers

### POST /bird-flock/webhooks/twilio/status

- Name: `bird-flock.twilio.status`
- Controller: `TwilioWebhookController::status`
- Required payload fields: `MessageSid`, `MessageStatus`
- Security: requires `TWILIO_AUTH_TOKEN` + signature validation
- Responses: `200`, `400`, `401`, `500`

### POST /bird-flock/webhooks/twilio/inbound

- Name: `bird-flock.twilio.inbound`
- Controller: `TwilioWebhookController::inbound`
- Security and response codes follow the same guard as above

### POST /bird-flock/webhooks/sendgrid/events

- Name: `bird-flock.sendgrid.events`
- Controller: `SendgridWebhookController::events`
- Request body: JSON array with event objects (`sg_message_id`, `event`)
- Security: `SENDGRID_REQUIRE_SIGNED_WEBHOOKS` controls signature enforcement
- Responses: `200`, `401` (plus potential `500` on config/runtime issues)

### POST /bird-flock/webhooks/vonage/delivery-receipt

- Name: `bird-flock.vonage.dlr`
- Controller: `VonageWebhookController::deliveryReceipt`
- Required fields: `messageId`, `status`
- Security: Vonage signature + timestamp checks when required
- Responses: `200`, `400`, `401`, `500`

### POST /bird-flock/webhooks/vonage/inbound

- Name: `bird-flock.vonage.inbound`
- Controller: `VonageWebhookController::inbound`
- Security and response codes follow same guard as delivery receipt

### POST /bird-flock/webhooks/mailgun/events

- Name: `bird-flock.mailgun.events`
- Controller: `MailgunWebhookController::events`
- Required data: `event-data.event`, `event-data.message.headers.message-id`
- Security: Mailgun signature + timestamp checks when required
- Responses: `200`, `400`, `401`, `500`

### GET /bird-flock/webhooks/labsmobile/ack

- Name: `bird-flock.labsmobile.ack`
- Controller: `LabsmobileWebhookController::ack`
- Required query fields: `subid`, `msisdn`, `status`, `acklevel`
- Security: optional `LABSMOBILE_WEBHOOK_TOKEN` matched against `token` query parameter
- Responses: `200`, `400`, `401`

## Status Mapping Summary

- Twilio: maps provider statuses to internal `queued|sending|sent|delivered|failed|undeliverable`.
- SendGrid: event mapping includes `processed->sending`, `delivered->delivered`, bounce-like events to failures.
- Vonage: maps delivery receipt statuses to internal state.
- Mailgun: maps event types such as `accepted`, `delivered`, `failed`, `rejected`, `complained`, `unsubscribed`.
- LabsMobile: maps `handset/status=ok` to `delivered`, gateway/operator ACKs to `sent`, and errors to `failed`.

## Assumptions

- These endpoints are integration endpoints for providers and ops tooling, not end-user APIs.

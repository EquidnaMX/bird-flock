<?php

/**
 * Controller for Mailgun webhook handling.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Http\Controllers
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Event;
use Equidna\BirdFlock\Contracts\OutboundMessageRepositoryInterface;
use Equidna\BirdFlock\Events\WebhookReceived;
use Equidna\BirdFlock\Support\MailgunSignatureValidator;
use Equidna\BirdFlock\Support\Logger;

/**
 * Handles Mailgun webhook events.
 */
final class MailgunWebhookController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @param OutboundMessageRepositoryInterface $repository Message repository
     */
    public function __construct(
        private readonly OutboundMessageRepositoryInterface $repository,
    ) {
        //
    }

    /**
     * Handle events from Mailgun.
     *
     * @param Request $request HTTP request
     *
     * @return Response
     */
    public function events(Request $request): Response
    {
        if ($response = $this->guardRequest($request)) {
            return $response;
        }

        $eventData = $request->input('event-data');

        if (!$eventData || !is_array($eventData)) {
            Logger::warning('bird-flock.webhook.mailgun.invalid_payload');
            return response('Invalid payload', 400);
        }

        $event = $eventData['event'] ?? null;
        $messageId = $eventData['message']['headers']['message-id'] ?? null;

        if (!$event || !$messageId) {
            Logger::warning('bird-flock.webhook.mailgun.missing_fields', [
                'event' => $event,
                'message_id' => $messageId,
            ]);
            return response('Missing required fields', 400);
        }

        $status = $this->mapMailgunEvent($event);

        $meta = [
            'provider_message_id' => $messageId,
            'event' => $event,
        ];

        if (in_array($event, ['failed', 'rejected'])) {
            $meta['error_code'] = $eventData['delivery-status']['code'] ?? 'unknown';
            $meta['error_message'] = $eventData['delivery-status']['message'] ?? '';
            $meta['failure_reason'] = $eventData['reason'] ?? '';
        }

        // Extract additional useful metadata
        if (isset($eventData['recipient'])) {
            $meta['recipient'] = $eventData['recipient'];
        }

        if (isset($eventData['timestamp'])) {
            $meta['event_timestamp'] = $eventData['timestamp'];
        }

        $this->repository->updateStatus(
            id: $messageId,
            status: $status,
            meta: $meta,
        );

        Logger::info('bird-flock.webhook.mailgun.event_processed', [
            'message_id' => $messageId,
            'event' => $event,
            'status' => $status,
        ]);

        Event::dispatch(new WebhookReceived(
            provider: 'mailgun',
            type: $event,
            payload: $eventData
        ));

        return response('OK', 200);
    }

    /**
     * Map Mailgun event to internal status.
     *
     * @param string $event Mailgun event type
     *
     * @return string Internal status
     */
    private function mapMailgunEvent(string $event): string
    {
        return match ($event) {
            'accepted' => 'queued',
            'delivered' => 'delivered',
            'failed', 'rejected' => 'failed',
            'complained' => 'undeliverable',
            'unsubscribed' => 'undeliverable',
            default => 'sent',
        };
    }

    /**
     * Validate the webhook signature and configuration.
     *
     * @param Request $request HTTP request
     *
     * @return Response|null
     */
    private function guardRequest(Request $request): ?Response
    {
        $signingKey = config('bird-flock.mailgun.webhook_signing_key');
        $requireSigned = config('bird-flock.mailgun.require_signed_webhooks', true);

        if ($requireSigned && !$signingKey) {
            Logger::error('bird-flock.webhook.mailgun.missing_signing_key');
            return response('Mailgun signing key not configured', 500);
        }

        if ($requireSigned) {
            if (!MailgunSignatureValidator::validate($request, $signingKey)) {
                Logger::warning('bird-flock.webhook.mailgun.invalid_signature');
                return response('Unauthorized', 401);
            }

            if (!MailgunSignatureValidator::validateTimestamp($request)) {
                Logger::warning('bird-flock.webhook.mailgun.timestamp_expired');
                return response('Request expired', 401);
            }
        }

        return null;
    }
}

<?php

/**
 * Controller for SendGrid webhook handling.
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
// Equidna Toolkit response helpers can be used when available.
use Equidna\BirdFlock\Contracts\OutboundMessageRepositoryInterface;
use Equidna\BirdFlock\Events\WebhookReceived;
use Equidna\BirdFlock\Support\SendgridSignatureValidator;
use Equidna\BirdFlock\Support\Logger;

/**
 * Handles SendGrid webhook events.
 */
final class SendgridWebhookController extends Controller
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
     * Handle events from SendGrid.
     *
     * @param Request $request HTTP request
     *
     * @return Response
     */
    public function events(Request $request): Response
    {
        if (!$this->isAuthorized($request)) {
            Logger::warning('bird-flock.webhook.sendgrid.unauthorized');
            return response('Unauthorized', 401);
        }

        $events = $request->json()->all();
        $processed = 0;

        foreach ($events as $event) {
            $messageId = $event['sg_message_id'] ?? null;
            $eventType = $event['event'] ?? null;

            if (!$messageId || !$eventType) {
                Logger::warning('bird-flock.webhook.sendgrid.malformed_event', [
                    'payload' => $event,
                ]);
                continue;
            }

            $status = $this->mapSendGridEvent($eventType);

            $meta = [
                'provider_message_id' => $messageId,
            ];

            if (in_array($eventType, ['bounce', 'dropped', 'spamreport'])) {
                $meta['error_code'] = $event['reason'] ?? 'unknown';
                $meta['error_message'] = $event['type'] ?? '';
            }

            $this->repository->updateStatus(
                id: $messageId,
                status: $status,
                meta: $meta,
            );

            Logger::info('bird-flock.webhook.sendgrid.event_processed', [
                'message_id' => $messageId,
                'event' => $eventType,
                'status' => $status,
            ]);

            Event::dispatch(new WebhookReceived(
                provider: 'sendgrid',
                type: $eventType,
                payload: $event
            ));
            $processed++;
        }

        Logger::info('bird-flock.webhook.sendgrid.batch_summary', [
            'count' => $processed,
        ]);

        return response('OK', 200);
    }

    /**
     * Map SendGrid event to internal status.
     *
     * @param string $eventType SendGrid event type
     *
     * @return string Internal status
     */
    private function mapSendGridEvent(string $eventType): string
    {
        return match ($eventType) {
            'processed' => 'sending',
            'delivered' => 'delivered',
            'bounce', 'dropped' => 'failed',
            'spamreport', 'blocked' => 'undeliverable',
            default => 'sent',
        };
    }

    /**
     * Determine if the incoming webhook request is authorized.
     *
     * @param Request $request
     *
     * @return bool
     */
    protected function isAuthorized(Request $request): bool
    {
        $requireSigned = config('bird-flock.sendgrid.require_signed_webhooks', true);
        $publicKey = config('bird-flock.sendgrid.webhook_public_key');

        if ($requireSigned && !$publicKey) {
            throw new \RuntimeException('SendGrid webhook public key is required but not configured.');
        }

        if (!$requireSigned) {
            return true;
        }

        if (!$publicKey) {
            Logger::warning('bird-flock.webhook.sendgrid.signature_missing_public_key');
            return false;
        }

        return SendgridSignatureValidator::validate($request, $publicKey);
    }
}

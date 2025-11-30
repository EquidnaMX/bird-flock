<?php

/**
 * Controller for Vonage webhook handling.
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
use Equidna\BirdFlock\Support\VonageSignatureValidator;
use Equidna\BirdFlock\Support\Logger;

/**
 * Handles Vonage delivery receipt callbacks.
 */
final class VonageWebhookController extends Controller
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
     * Handle delivery receipt (DLR) from Vonage.
     *
     * @param Request $request HTTP request
     *
     * @return Response
     */
    public function deliveryReceipt(Request $request): Response
    {
        if ($response = $this->guardRequest($request)) {
            return $response;
        }

        // Vonage sends messageId and status
        $messageId = $request->input('messageId');
        $vonageStatus = $request->input('status');

        if (!is_string($messageId) || !is_string($vonageStatus)) {
            Logger::warning('bird-flock.webhook.vonage.invalid_input', [
                'message_id' => $messageId,
                'status' => $vonageStatus,
            ]);
            return response('Invalid input', 400);
        }

        $status = $this->mapVonageStatus($vonageStatus);

        $meta = [
            'provider_message_id' => $messageId,
            'network_code' => $request->input('network-code'),
        ];

        if ($status === 'failed') {
            $meta['error_code'] = $request->input('err-code');
            $meta['error_message'] = $this->getErrorMessage($request->input('err-code'));
        }

        $this->repository->updateStatus(
            id: $messageId,
            status: $status,
            meta: $meta,
        );

        Logger::info('bird-flock.webhook.vonage.dlr', [
            'message_id' => $messageId,
            'vonage_status' => $vonageStatus,
            'status' => $status,
        ]);

        Event::dispatch(new WebhookReceived(
            provider: 'vonage',
            type: 'delivery_receipt',
            payload: $request->all()
        ));

        return response('OK', 200);
    }

    /**
     * Handle inbound message from Vonage.
     *
     * @param Request $request HTTP request
     *
     * @return Response
     */
    public function inbound(Request $request): Response
    {
        if ($response = $this->guardRequest($request)) {
            return $response;
        }

        Logger::info('bird-flock.webhook.vonage.inbound', [
            'from' => $request->input('msisdn'),
            'to' => $request->input('to'),
        ]);

        Event::dispatch(new WebhookReceived(
            provider: 'vonage',
            type: 'inbound',
            payload: $request->all()
        ));

        return response('OK', 200);
    }

    /**
     * Map Vonage status to internal status.
     *
     * @param string $vonageStatus Vonage status value
     *
     * @return string Internal status
     */
    private function mapVonageStatus(string $vonageStatus): string
    {
        return match ($vonageStatus) {
            'delivered' => 'delivered',
            'accepted', 'buffered' => 'sending',
            'expired', 'failed', 'rejected' => 'failed',
            'unknown' => 'undeliverable',
            default => 'sent',
        };
    }

    /**
     * Get human-readable error message for Vonage error code.
     *
     * @param string|null $errorCode Vonage error code
     *
     * @return string Error message
     */
    private function getErrorMessage(?string $errorCode): string
    {
        if (!$errorCode) {
            return 'Unknown error';
        }

        // Common Vonage error codes
        return match ($errorCode) {
            '1' => 'Unknown error',
            '2' => 'Absent subscriber temporary',
            '3' => 'Absent subscriber permanent',
            '4' => 'Call barred by user',
            '5' => 'Portability error',
            '6' => 'Anti-spam rejection',
            '7' => 'Handset busy',
            '8' => 'Network error',
            '9' => 'Illegal number',
            '10' => 'Invalid message',
            '11' => 'Unroutable',
            '12' => 'Destination unreachable',
            '13' => 'Subscriber age restriction',
            '14' => 'Number blocked by carrier',
            '15' => 'Pre-paid insufficient funds',
            default => "Error code: {$errorCode}",
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
        $signatureSecret = config('bird-flock.vonage.signature_secret');
        $requireSigned = config('bird-flock.vonage.require_signed_webhooks', true);

        if ($requireSigned && !$signatureSecret) {
            Logger::error('bird-flock.webhook.vonage.missing_signature_secret');
            return response('Vonage signature secret not configured', 500);
        }

        if ($requireSigned) {
            if (!VonageSignatureValidator::validate($request, $signatureSecret)) {
                Logger::warning('bird-flock.webhook.vonage.invalid_signature', [
                    'url' => $request->fullUrl(),
                ]);
                return response('Unauthorized', 401);
            }

            if (!VonageSignatureValidator::validateTimestamp($request)) {
                Logger::warning('bird-flock.webhook.vonage.timestamp_expired');
                return response('Request expired', 401);
            }
        }

        return null;
    }
}

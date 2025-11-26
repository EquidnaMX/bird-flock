<?php

/**
 * Controller for Twilio webhook handling.
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
use Equidna\BirdFlock\Support\TwilioSignatureValidator;
use Equidna\BirdFlock\Support\Logger;

/**
 * Handles Twilio webhook callbacks.
 */
final class TwilioWebhookController extends Controller
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
     * Handle status callback from Twilio.
     *
     * @param Request $request HTTP request
     *
     * @return Response
     */
    public function status(Request $request): Response
    {
        if ($response = $this->guardRequest($request)) {
            return $response;
        }

        $messageSid = $request->input('MessageSid');
        $messageStatus = $request->input('MessageStatus');

        $status = $this->mapTwilioStatus($messageStatus);

        $meta = [
            'provider_message_id' => $messageSid,
        ];

        if ($status === 'failed') {
            $meta['error_code'] = $request->input('ErrorCode');
            $meta['error_message'] = $request->input('ErrorMessage');
        }

        $this->repository->updateStatus(
            id: $messageSid,
            status: $status,
            meta: $meta,
        );

        Logger::info('bird-flock.webhook.twilio.status', [
            'message_id' => $messageSid,
            'provider_status' => $messageStatus,
            'status' => $status,
        ]);

        Event::dispatch(new WebhookReceived(
            provider: 'twilio',
            type: 'status',
            payload: $request->all()
        ));

        return response('OK', 200);
    }

    /**
     * Handle inbound message from Twilio.
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

        Logger::info('bird-flock.webhook.twilio.inbound', [
            'from' => $request->input('From'),
            'to' => $request->input('To'),
        ]);

        Event::dispatch(new WebhookReceived(
            provider: 'twilio',
            type: 'inbound',
            payload: $request->all()
        ));

        return response('OK', 200);
    }

    /**
     * Map Twilio status to internal status.
     *
     * @param string $twilioStatus Twilio status value
     *
     * @return string Internal status
     */
    private function mapTwilioStatus(string $twilioStatus): string
    {
        return match ($twilioStatus) {
            'queued' => 'queued',
            'sending' => 'sending',
            'sent' => 'sent',
            'delivered' => 'delivered',
            'failed', 'undelivered' => 'failed',
            default => 'undeliverable',
        };
    }

    /**
     * Validate the webhook signature and configuration.
     *
     * @param Request $request
     *
     * @return Response|null
     */
    private function guardRequest(Request $request): ?Response
    {
        $authToken = config('bird-flock.twilio.auth_token');

        if (!$authToken) {
            Logger::error('bird-flock.webhook.twilio.missing_auth_token');

            return response('Twilio auth token not configured', 500);
        }

        $url = $request->fullUrl();

        if (!TwilioSignatureValidator::validate($request, $authToken, $url)) {
            Logger::warning('bird-flock.webhook.twilio.invalid_signature', [
                'url' => $url,
            ]);
            return response('Unauthorized', 401);
        }

        return null;
    }
}

<?php

/**
 * Controller for LabsMobile webhook handling.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Http\Controllers
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Http\Controllers;

use Equidna\BirdFlock\Contracts\OutboundMessageRepositoryInterface;
use Equidna\BirdFlock\Events\WebhookReceived;
use Equidna\BirdFlock\Support\Logger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Event;

/**
 * Handles LabsMobile ACK callbacks.
 */
final class LabsmobileWebhookController extends Controller
{
    public function __construct(
        private readonly OutboundMessageRepositoryInterface $repository,
    ) {
        //
    }

    public function ack(Request $request): Response
    {
        if ($response = $this->guardRequest($request)) {
            return $response;
        }

        $subid = $request->query('subid');
        $msisdn = $request->query('msisdn');
        $providerStatus = $request->query('status');
        $ackLevel = $request->query('acklevel');

        if (!is_string($subid) || !is_string($msisdn) || !is_string($providerStatus) || !is_string($ackLevel)) {
            Logger::warning('bird-flock.webhook.labsmobile.invalid_input', [
                'subid' => $subid,
                'msisdn' => $msisdn,
                'status' => $providerStatus,
                'acklevel' => $ackLevel,
            ]);

            return response('Invalid input', 400);
        }

        $status = $this->mapStatus($ackLevel, $providerStatus);

        $meta = [
            'provider_message_id' => $subid,
            'msisdn' => $msisdn,
            'acklevel' => $ackLevel,
            'provider_status' => $providerStatus,
            'timestamp' => $request->query('timestamp'),
        ];

        if ($status === 'failed') {
            $meta['error_code'] = $request->query('desc');
            $meta['error_message'] = $request->query('desc');
        }

        $this->repository->updateStatus(
            id: $subid,
            status: $status,
            meta: $meta,
        );

        Logger::info('bird-flock.webhook.labsmobile.ack', [
            'message_id' => $subid,
            'acklevel' => $ackLevel,
            'provider_status' => $providerStatus,
            'status' => $status,
        ]);

        Event::dispatch(new WebhookReceived(
            provider: 'labsmobile',
            type: 'ack',
            payload: $request->query()
        ));

        return response('OK', 200);
    }

    private function guardRequest(Request $request): ?Response
    {
        $token = config('bird-flock-labsmobile.webhook_token');

        if (!$token) {
            return null;
        }

        $requestToken = $request->query('token');
        if (!is_string($requestToken) || !hash_equals((string) $token, $requestToken)) {
            Logger::warning('bird-flock.webhook.labsmobile.invalid_token');

            return response('Unauthorized', 401);
        }

        return null;
    }

    private function mapStatus(string $ackLevel, string $providerStatus): string
    {
        $ackLevel = strtolower(trim($ackLevel));
        $providerStatus = strtolower(trim($providerStatus));

        if ($providerStatus === 'ko' || $ackLevel === 'error') {
            return 'failed';
        }

        if ($providerStatus === 'ok' && $ackLevel === 'handset') {
            return 'delivered';
        }

        if ($providerStatus === 'ok' && in_array($ackLevel, ['gateway', 'operator'], true)) {
            return 'sent';
        }

        return 'sent';
    }
}

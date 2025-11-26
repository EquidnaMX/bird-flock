<?php

namespace Equidna\BirdFlock\Tests\Messaging\Support;

use PHPUnit\Framework\TestCase;
use Illuminate\Http\Request;
use Equidna\BirdFlock\Support\TwilioSignatureValidator;

class TwilioSignatureValidatorTest extends TestCase
{
    public function testValidateWithCorrectSignature(): void
    {
        $authToken = 'test_auth_token';
        $url = 'https://example.com/webhook';
        $params = [
            'MessageSid' => 'SM123',
            'AccountSid' => 'AC456',
            'From' => '+15005550006',
            'To' => '+15005550001',
            'Body' => 'Hello',
        ];

        ksort($params);
        $data = $url;
        foreach ($params as $key => $value) {
            $data .= $key . $value;
        }

        $signature = base64_encode(hash_hmac('sha1', $data, $authToken, true));

        $request = Request::create($url, 'POST', $params);
        $request->headers->set('X-Twilio-Signature', $signature);

        $result = TwilioSignatureValidator::validate($request, $authToken, $url);
        $this->assertTrue($result);
    }

    public function testValidateWithIncorrectSignature(): void
    {
        $authToken = 'test_auth_token';
        $url = 'https://example.com/webhook';
        $params = ['MessageSid' => 'SM123'];

        $request = Request::create($url, 'POST', $params);
        $request->headers->set('X-Twilio-Signature', 'invalid_signature');

        $result = TwilioSignatureValidator::validate($request, $authToken, $url);
        $this->assertFalse($result);
    }

    public function testValidateWithMissingSignature(): void
    {
        $authToken = 'test_auth_token';
        $url = 'https://example.com/webhook';

        $request = Request::create($url, 'POST', []);

        $result = TwilioSignatureValidator::validate($request, $authToken, $url);
        $this->assertFalse($result);
    }
}






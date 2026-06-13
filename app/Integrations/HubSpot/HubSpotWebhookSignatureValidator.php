<?php

namespace App\Integrations\HubSpot;

use Illuminate\Http\Request;

class HubSpotWebhookSignatureValidator
{
    public function isValid(Request $request): bool
    {
        if (config('hubspot.skip_webhook_signature_validation')) {
            return true;
        }

        $secret = (string) config('hubspot.client_secret');

        if ($secret === '') {
            return false;
        }

        $signatureV3 = $request->header('X-HubSpot-Signature-v3');
        $timestamp = $request->header('X-HubSpot-Request-Timestamp');

        if (is_string($signatureV3) && is_string($timestamp)) {
            $source = $request->method()
                .$request->getRequestUri()
                .$request->getContent()
                .$timestamp;

            $expected = base64_encode(hash_hmac('sha256', $source, $secret, true));

            if (hash_equals($expected, $signatureV3)) {
                return abs((int) (microtime(true) * 1000) - (int) $timestamp) <= 300_000;
            }
        }

        $signatureV1 = $request->header('X-HubSpot-Signature');

        if (is_string($signatureV1)) {
            $expected = hash('sha256', $secret.$request->getContent());

            return hash_equals($expected, $signatureV1);
        }

        return false;
    }
}

<?php

namespace App\Http\Controllers;

use App\Integrations\HubSpot\HubSpotWebhookSignatureValidator;
use App\Jobs\ProcessHubSpotWebhookJob;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class HubSpotWebhookController extends Controller
{
    public function __invoke(Request $request, HubSpotWebhookSignatureValidator $signatureValidator): Response
    {
        if (! $signatureValidator->isValid($request)) {
            Log::channel('hubspot')->warning('HubSpot webhook rejected: invalid signature.');

            return response('', Response::HTTP_UNAUTHORIZED);
        }

        /** @var array<int, array<string, mixed>>|null $events */
        $events = $request->json()->all();

        if (! is_array($events)) {
            Log::channel('hubspot')->warning('HubSpot webhook rejected: invalid payload.');

            return response('', Response::HTTP_BAD_REQUEST);
        }

        Log::channel('hubspot')->info('HubSpot webhook received.', [
            'events_count' => count($events),
        ]);

        ProcessHubSpotWebhookJob::dispatch($events);

        return response('', Response::HTTP_NO_CONTENT);
    }
}

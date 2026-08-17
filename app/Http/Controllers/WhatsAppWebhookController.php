<?php

namespace App\Http\Controllers;

use App\Models\RsmCrmLead;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Receives Meta's WhatsApp Cloud API webhook (public, unauthenticated —
 * see routes/web.php + bootstrap/app.php's CSRF exception for
 * webhooks/*). Only messages carrying a `referral` object are ingested:
 * that's Meta's marker that the message originated from a Click To
 * WhatsApp Ads (CTWA) click, as opposed to an organic WhatsApp message to
 * the business number.
 */
class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = (string) $request->query('hub_challenge', '');

        $verifyToken = (string) config('services.whatsapp.verify_token');
        if ($mode === 'subscribe' && $verifyToken !== '' && hash_equals($verifyToken, (string) $token)) {
            return response($challenge, 200);
        }

        return response('', 403);
    }

    public function receive(Request $request): Response
    {
        if (! $this->hasValidSignature($request)) {
            Log::warning('whatsapp webhook: invalid signature');

            return response('', 403);
        }

        try {
            $this->ingest(json_decode($request->getContent(), true) ?? []);
        } catch (\Throwable $e) {
            Log::warning('whatsapp webhook: failed to process payload', ['error' => $e->getMessage()]);
        }

        return response('', 200);
    }

    private function hasValidSignature(Request $request): bool
    {
        $secret = (string) config('services.whatsapp.app_secret');
        if ($secret === '') {
            return false;
        }

        $header = (string) $request->header('X-Hub-Signature-256', '');
        if (! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $header);
    }

    private function ingest(array $payload): void
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? null) !== 'messages') {
                    continue;
                }
                $this->ingestValue($change['value'] ?? []);
            }
        }
    }

    private function ingestValue(array $value): void
    {
        $contact = $value['contacts'][0] ?? [];
        foreach ($value['messages'] ?? [] as $message) {
            $referral = $message['referral'] ?? null;
            if (! is_array($referral)) {
                continue;
            }

            $messageId = (string) ($message['id'] ?? '');
            if ($messageId === '') {
                continue;
            }

            $notes = collect([
                $referral['headline'] ?? null,
                $referral['body'] ?? null,
                $referral['source_url'] ?? null,
            ])->filter()->implode("\n");

            RsmCrmLead::firstOrCreate(
                ['wa_message_id' => $messageId],
                [
                    'area' => 'Regional B',
                    'lead_name' => $contact['profile']['name'] ?? 'Lead WhatsApp',
                    'whatsapp' => $contact['wa_id'] ?? ($message['from'] ?? null),
                    'source' => 'CTWA',
                    'status' => 'Baru',
                    'ctwa_clid' => $referral['ctwa_clid'] ?? null,
                    'notes' => $notes !== '' ? $notes : null,
                ]
            );
        }
    }
}

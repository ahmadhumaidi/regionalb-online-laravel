<?php

namespace Tests\Feature;

use App\Models\RsmCrmLead;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Meta WhatsApp Cloud API webhook (WhatsAppWebhookController) — the GET
 * handshake Meta uses to confirm the Callback URL, and the POST delivery
 * of inbound messages. Only messages carrying a `referral` object (i.e.
 * originated from a Click To WhatsApp Ads click) are turned into CRM
 * leads; plain organic WhatsApp messages are ignored entirely.
 */
class WhatsAppWebhookTest extends TestCase
{
    private const VERIFY_TOKEN = 'test_verify_token';

    private const APP_SECRET = 'test_app_secret';

    private function migrate(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_17_090000_create_rsm_crm_leads_table.php',
            'database/migrations/2026_08_17_100000_add_whatsapp_fields_to_rsm_crm_leads_table.php',
        ]]);
        config([
            'services.whatsapp.verify_token' => self::VERIFY_TOKEN,
            'services.whatsapp.app_secret' => self::APP_SECRET,
        ]);
    }

    private function ctwaPayload(string $messageId = 'wamid.TEST1'): array
    {
        return [
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'contacts' => [['wa_id' => '628123456789', 'profile' => ['name' => 'Budi Santoso']]],
                        'messages' => [[
                            'id' => $messageId,
                            'from' => '628123456789',
                            'referral' => [
                                'source_url' => 'https://fb.me/adslink',
                                'headline' => 'Promo Kampus',
                                'body' => 'Klik dari iklan CTWA',
                                'ctwa_clid' => 'clid-abc-123',
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    private function signatureFor(array $payload): string
    {
        return 'sha256='.hash_hmac('sha256', json_encode($payload), self::APP_SECRET);
    }

    public function test_verify_handshake_succeeds_with_correct_token(): void
    {
        $this->migrate();

        $response = $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token='.self::VERIFY_TOKEN.'&hub_challenge=echo123');

        $response->assertOk();
        $response->assertSee('echo123', false);
    }

    public function test_verify_handshake_rejects_wrong_token(): void
    {
        $this->migrate();

        $response = $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=echo123');

        $response->assertForbidden();
    }

    public function test_receive_creates_lead_from_ctwa_referral(): void
    {
        $this->migrate();
        $payload = $this->ctwaPayload();

        $response = $this->postJson('/webhooks/whatsapp', $payload, ['X-Hub-Signature-256' => $this->signatureFor($payload)]);

        $response->assertOk();
        $this->assertDatabaseHas('rsm_crm_leads', [
            'wa_message_id' => 'wamid.TEST1', 'lead_name' => 'Budi Santoso', 'whatsapp' => '628123456789',
            'source' => 'CTWA', 'status' => 'Baru', 'ctwa_clid' => 'clid-abc-123',
        ]);
        $lead = RsmCrmLead::where('wa_message_id', 'wamid.TEST1')->first();
        $this->assertNull($lead->owner_user_id);
        $this->assertNull($lead->wilayah);

        RsmCrmLead::query()->delete();
    }

    public function test_receive_is_idempotent_for_the_same_message_id(): void
    {
        $this->migrate();
        $payload = $this->ctwaPayload('wamid.DUPLICATE');
        $signature = $this->signatureFor($payload);

        $this->postJson('/webhooks/whatsapp', $payload, ['X-Hub-Signature-256' => $signature])->assertOk();
        $this->postJson('/webhooks/whatsapp', $payload, ['X-Hub-Signature-256' => $signature])->assertOk();

        $this->assertSame(1, RsmCrmLead::where('wa_message_id', 'wamid.DUPLICATE')->count());

        RsmCrmLead::query()->delete();
    }

    public function test_receive_ignores_messages_without_referral(): void
    {
        $this->migrate();
        $payload = [
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'contacts' => [['wa_id' => '628999999999', 'profile' => ['name' => 'Organic Chatter']]],
                        'messages' => [['id' => 'wamid.ORGANIC1', 'from' => '628999999999', 'text' => ['body' => 'halo']]],
                    ],
                ]],
            ]],
        ];

        $response = $this->postJson('/webhooks/whatsapp', $payload, ['X-Hub-Signature-256' => $this->signatureFor($payload)]);

        $response->assertOk();
        $this->assertDatabaseCount('rsm_crm_leads', 0);
    }

    public function test_receive_rejects_invalid_signature(): void
    {
        $this->migrate();
        $payload = $this->ctwaPayload('wamid.SHOULDNOTEXIST');

        $response = $this->postJson('/webhooks/whatsapp', $payload, ['X-Hub-Signature-256' => 'sha256=deadbeef']);

        $response->assertForbidden();
        $this->assertDatabaseCount('rsm_crm_leads', 0);
    }
}

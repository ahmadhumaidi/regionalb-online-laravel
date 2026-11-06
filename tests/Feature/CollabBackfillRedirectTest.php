<?php

namespace Tests\Feature;

use App\Services\CollabSourceService;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

class CollabBackfillRedirectTest extends TestCase
{
    public function test_backfill_follows_session_filter_redirect_to_the_same_report(): void
    {
        $url = CollabSourceService::sourceUrl('Pasang Spanduk');
        Http::preventStrayRequests();
        Http::fake([$url => Http::sequence()
            ->push("<script>document.location='data_pasang_spanduk_peregional.php';</script>")
            ->push('<table><tr><td>Sep 2026</td></tr><tr><td>01</td></tr><tr><td>6</td></tr></table>')]);

        $report = (new ReflectionMethod(CollabSourceService::class, 'reportFromRange'))
            ->invoke(null, 'Pasang Spanduk', '01/09/2026', '30/09/2026', new CookieJar);

        $this->assertSame('2026-09', CollabSourceService::reportMonth($report['tables'][0]));
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['filter_dayone'] === '01/09/2026');
        Http::assertSent(fn ($request) => $request->method() === 'GET' && $request->url() === $url);
    }
}

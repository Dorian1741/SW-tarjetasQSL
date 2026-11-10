<?php

namespace Tests\Feature;

use App\Services\Callbook\CallbookTransport;
use App\Services\Callbook\HamQthCallbookProvider;
use App\Services\Callbook\QrzCallbookProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CallbookProvidersTest extends TestCase
{
    private function enable(): void
    {
        config(['cache.default' => 'array', 'callbook.qrz.enabled' => true, 'callbook.qrz.username' => 'test-user', 'callbook.qrz.password' => 'test-secret', 'callbook.hamqth.enabled' => true, 'callbook.hamqth.username' => 'TEST0USER', 'callbook.hamqth.password' => 'test-secret']);
        Cache::flush();
        Http::preventStrayRequests();
    }

    public function test_qrz_namespaced_xml_and_private_fields_are_filtered_and_token_is_reused(): void
    {
        $this->enable();
        Http::fake(['https://xmldata.qrz.com/xml/current/' => Http::sequence()->push('<QRZDatabase xmlns="http://xmldata.qrz.com"><Session><Key>secret-token</Key></Session></QRZDatabase>')->push('<QRZDatabase xmlns="http://xmldata.qrz.com"><Session><Key>secret-token</Key></Session><Callsign><call>HC1VTB</call><country>Ecuador</country><email>private@example.test</email><fname>Private</fname></Callsign></QRZDatabase>')->push('<QRZDatabase><Session><Key>secret-token</Key><Error>Not found: HC1VTB</Error></Session></QRZDatabase>')]);
        $result = app(QrzCallbookProvider::class)->lookup('HC1VTB');
        $this->assertSame('found', $result->status);
        $this->assertSame(['callsign' => 'HC1VTB', 'country' => 'Ecuador'], $result->record);
        $this->assertSame('not_found', app(QrzCallbookProvider::class)->lookup('HC1VTB')->status);
        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => $request->method() === 'POST' && ($request->data()['username'] ?? null) === 'test-user' && ! str_contains($request->url(), 'test-secret'));
        $cached = Cache::get(app(CallbookTransport::class)->key('qrz').':token');
        $this->assertNotSame('secret-token', $cached);
    }

    public function test_qrz_expired_session_is_renewed_once(): void
    {
        $this->enable();
        Http::fake(['https://xmldata.qrz.com/xml/current/' => Http::sequence()->push('<QRZDatabase><Session><Key>old</Key></Session></QRZDatabase>')->push('<QRZDatabase><Session><Error>Session Timeout</Error></Session></QRZDatabase>')->push('<QRZDatabase><Session><Key>new</Key></Session></QRZDatabase>')->push('<QRZDatabase><Session><Key>new</Key></Session><Callsign><call>HC1VTB</call></Callsign></QRZDatabase>')]);
        $this->assertSame('found', app(QrzCallbookProvider::class)->lookup('HC1VTB')->status);
        Http::assertSentCount(4);
    }

    public function test_xml_entities_and_incomplete_records_are_not_accepted(): void
    {
        $this->enable();
        Http::fake(['https://xmldata.qrz.com/xml/current/' => Http::sequence()->push('<QRZDatabase><Session><Key>t</Key></Session></QRZDatabase>')->push('<!DOCTYPE foo [<!ENTITY secret SYSTEM "file:///etc/passwd">]><QRZDatabase><Callsign><call>&secret;</call></Callsign></QRZDatabase>')]);
        $this->assertSame('incomplete', app(QrzCallbookProvider::class)->lookup('HC1VTB')->status);
    }

    public function test_hamqth_uses_v2_post_login_and_bearer_and_handles_portable(): void
    {
        $this->enable();
        Http::fake(['https://www.hamqth.com/api/v2/login' => Http::response(['token' => 'token-v2', 'expiresIn' => 3600]), 'https://www.hamqth.com/api/v2/callbook/*' => Http::response(['callsign' => 'HC1VTB', 'country' => 'Ecuador', 'email' => 'private@example.test', 'address' => ['street' => 'Private address']])]);
        $result = app(HamQthCallbookProvider::class)->lookup('HC1VTB/P');
        $this->assertSame('found', $result->status);
        $this->assertSame(['callsign' => 'HC1VTB', 'country' => 'Ecuador'], $result->record);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/api/v2/callbook/HC1VTB%2FP') && $r->hasHeader('Authorization', 'Bearer token-v2'));
    }

    public function test_hamqth_404_differs_from_outage_and_incomplete_response(): void
    {
        $this->enable();
        Http::fake(['https://www.hamqth.com/api/v2/login' => Http::response(['token' => 't', 'expiresIn' => 3600]), 'https://www.hamqth.com/api/v2/callbook/*' => Http::sequence()->push([], 404)->push(['country' => 'Ecuador'])->pushStatus(503)]);
        $provider = app(HamQthCallbookProvider::class);
        $this->assertSame('not_found', $provider->lookup('HC1VTB')->status);
        $this->assertSame('incomplete', $provider->lookup('HC1VTB')->status);
        $this->assertSame('unavailable', $provider->lookup('HC1VTB')->status);
    }

    public function test_timeout_does_not_leak_credentials_or_claim_not_found(): void
    {
        $this->enable();
        Http::fake(['https://www.hamqth.com/api/v2/login' => Http::failedConnection('timeout test-secret')]);
        $result = app(HamQthCallbookProvider::class)->lookup('HC1VTB');
        $this->assertSame('unavailable', $result->status);
        $this->assertStringNotContainsString('test-secret', json_encode($result->toArray()));
    }

    public function test_rate_limited_provider_is_not_hammered(): void
    {
        $this->enable();
        Http::fake(['https://www.hamqth.com/api/v2/login' => Http::response(['error' => 'limit'], 429, ['Retry-After' => '60'])]);
        $provider = app(HamQthCallbookProvider::class);
        $this->assertSame('unavailable', $provider->lookup('HC1VTB')->status);
        $this->assertSame('unavailable', $provider->lookup('HC1VTB')->status);
        Http::assertSentCount(1);
    }

    public function test_hamqth_expired_token_renews_once_and_rejects_unrelated_callsign(): void
    {
        $this->enable();
        Http::fake(['https://www.hamqth.com/api/v2/login' => Http::sequence()->push(['token' => 'old', 'expiresIn' => 3600])->push(['token' => 'new', 'expiresIn' => 3600]), 'https://www.hamqth.com/api/v2/callbook/*' => Http::sequence()->pushStatus(401)->push(['callsign' => 'UNRELATED1'])]);
        $this->assertSame('incomplete', app(HamQthCallbookProvider::class)->lookup('HC1VTB')->status);
        Http::assertSentCount(4);
    }

    public function test_unconfigured_provider_makes_no_request(): void
    {
        $this->enable();
        config(['callbook.qrz.enabled' => false]);
        $this->assertSame('unconfigured', app(QrzCallbookProvider::class)->lookup('HC1VTB')->status);
        Http::assertNothingSent();
    }
}

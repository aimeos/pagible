<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\Webhook;
use Aimeos\Cms\WebhookClient;
use Aimeos\Cms\WebhookException;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;


final class StubWebhookClient extends WebhookClient
{
    /** @var array<int, mixed> */
    public array $options = [];
    public ?string $overflow = null;


    /**
     * @param array<int, mixed> $options
     * @return array{bool, int}
     */
    protected function execute( array $options ) : array
    {
        $this->options = $options;

        if( $this->overflow === 'body' ) {
            call_user_func( $options[CURLOPT_WRITEFUNCTION], null, '12345' );
            return [false, 0];
        }

        if( $this->overflow === 'headers' ) {
            call_user_func( $options[CURLOPT_HEADERFUNCTION], null, '12345' );
            return [false, 0];
        }

        return [true, 204];
    }


    protected function resolve( string $host ) : string
    {
        return '93.184.216.34';
    }
}


class WebhookClientTest extends WebhookTestAbstract
{
    public function testCanonicalUrl() : void
    {
        $client = app( WebhookClient::class );

        $this->assertSame( 'https://example.com/hook?source=cms',
            $client->canonical( 'HTTPS://EXAMPLE.COM/hook?source=cms' ) );
    }


    public function testCanonicalizesBracketedIpv6Literal() : void
    {
        $this->assertSame(
            'https://[2606:4700:4700::1111]/hook',
            app( WebhookClient::class )->canonical( 'HTTPS://[2606:4700:4700::1111]/hook' ),
        );
    }


    #[DataProvider( 'invalidUrls' )]
    public function testRejectsUnsafeUrls( string $url ) : void
    {
        $this->expectException( WebhookException::class );
        app( WebhookClient::class )->canonical( $url );
    }


    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidUrls() : iterable
    {
        yield 'credentials' => ['https://user:pass@example.com/hook'];
        yield 'fragment' => ['https://example.com/hook#token'];
        yield 'loopback' => ['https://127.0.0.1/hook'];
        yield 'link local' => ['https://169.254.169.254/latest/meta-data'];
        yield 'shared address space' => ['https://100.100.100.200/hook'];
        yield 'benchmark address space' => ['https://198.18.0.1/hook'];
        yield 'single label' => ['https://localhost/hook'];
        yield 'http default' => ['http://example.com/hook'];
        yield 'nonstandard port' => ['https://example.com:8443/hook'];
        yield 'encoded authority' => ['https://example.com%40internal/hook'];
        yield 'bracketed hostname' => ['https://[example.com]/hook'];
        yield 'bracketed IPv4' => ['https://[93.184.216.34]/hook'];
    }


    public function testExactHostPolicyCanAllowInternalDestination() : void
    {
        config( ['cms.webhooks.hosts' => ['internal.example' => [
            'schemes' => ['http'],
            'ports' => [8080],
            'cidrs' => ['10.0.0.0/8'],
        ]]] );

        $this->assertSame(
            'http://internal.example:8080/hook',
            app( WebhookClient::class )->canonical( 'http://internal.example:8080/hook' ),
        );
    }


    public function testExactHostPolicyCanAllowNonGlobalDestination() : void
    {
        config( ['cms.webhooks.hosts' => ['100.100.100.200' => [
            'cidrs' => ['100.64.0.0/10'],
        ]]] );

        $this->assertSame(
            'https://100.100.100.200/hook',
            app( WebhookClient::class )->canonical( 'https://100.100.100.200/hook' ),
        );
    }


    public function testInvalidOperatorPolicyFailsValidation() : void
    {
        config( ['cms.webhooks.deny_cidrs' => ['not-a-cidr']] );
        $this->expectException( \LogicException::class );

        app( WebhookClient::class )->validatePolicy();
    }


    public function testUnknownHostPolicyKeyFailsValidation() : void
    {
        config( ['cms.webhooks.hosts' => ['example.com' => ['redirects' => true]]] );
        $this->expectException( \LogicException::class );

        app( WebhookClient::class )->validatePolicy();
    }


    public function testBuildsSignedPinnedRequest() : void
    {
        $client = new StubWebhookClient();
        $webhook = $this->model();
        $body = '{"event":"page.published"}';
        Carbon::setTestNow( '2026-09-14 12:00:00 UTC' );

        try {
            $this->assertSame( 204, $client->send( $webhook, 'page.published', 'delivery-id', $body ) );
        } finally {
            Carbon::setTestNow();
        }

        $timestamp = '1789387200';
        $signed = implode( "\n", [
            'v2',
            'x-cms-event:page.published',
            'x-cms-tenant:test',
            'x-cms-delivery:delivery-id',
            'x-cms-timestamp:' . $timestamp,
            '',
            $body,
        ] );
        $signature = hash_hmac( 'sha256', $signed, 'test-secret' );
        $headers = $client->options[CURLOPT_HTTPHEADER];

        $this->assertSame( 'https://example.com/hooks/cms', $client->options[CURLOPT_URL] );
        $this->assertSame( $body, $client->options[CURLOPT_POSTFIELDS] );
        $this->assertContains( 'Accept-Encoding: identity', $headers );
        $this->assertContains( 'Content-Type: application/json', $headers );
        $this->assertContains( 'Expect:', $headers );
        $this->assertContains( 'User-Agent: Pagible-Webhook/1.0', $headers );
        $this->assertContains( 'X-Cms-Event: page.published', $headers );
        $this->assertContains( 'X-Cms-Tenant: test', $headers );
        $this->assertContains( 'X-Cms-Delivery: delivery-id', $headers );
        $this->assertContains( 'X-Cms-Timestamp: ' . $timestamp, $headers );
        $this->assertContains( 'X-Cms-Signature: v2=' . $signature, $headers );
        $this->assertSame( ['example.com:443:93.184.216.34'], $client->options[CURLOPT_RESOLVE] );
        $this->assertSame( '', $client->options[CURLOPT_PROXY] );
        $this->assertSame( '*', $client->options[CURLOPT_NOPROXY] );
        $this->assertFalse( $client->options[CURLOPT_FOLLOWLOCATION] );
        $this->assertSame( 0, $client->options[CURLOPT_MAXREDIRS] );
        $this->assertTrue( $client->options[CURLOPT_SSL_VERIFYPEER] );
        $this->assertSame( 2, $client->options[CURLOPT_SSL_VERIFYHOST] );
        $this->assertSame( CURL_SSLVERSION_TLSv1_2, $client->options[CURLOPT_SSLVERSION] );

        if( defined( 'CURLOPT_PROTOCOLS_STR' ) ) {
            $this->assertSame( 'https', $client->options[(int) constant( 'CURLOPT_PROTOCOLS_STR' )] );
        } else {
            $this->assertSame( CURLPROTO_HTTPS, $client->options[CURLOPT_PROTOCOLS] );
        }
    }


    public function testSendsExplicitEmptyTenantHeader() : void
    {
        $client = new StubWebhookClient();

        $client->send( $this->model( '' ), 'page.published', 'delivery-id', '{}' );

        $this->assertContains( 'X-Cms-Tenant;', $client->options[CURLOPT_HTTPHEADER] );
    }


    #[DataProvider( 'responseLimits' )]
    public function testRejectsOversizedResponse( string $overflow, string $config, string $reason ) : void
    {
        config( [$config => 4] );
        $client = new StubWebhookClient();
        $client->overflow = $overflow;

        try {
            $client->send( $this->model(), 'page.published', 'delivery-id', '{}' );
            $this->fail( 'Expected an oversized response exception.' );
        } catch( WebhookException $e ) {
            $this->assertSame( $reason, $e->reason );
        }
    }


    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function responseLimits() : iterable
    {
        yield 'body' => ['body', 'cms.webhooks.http.max_body', 'response_body_too_large'];
        yield 'headers' => ['headers', 'cms.webhooks.http.max_headers', 'response_headers_too_large'];
    }


    public function testSendsLargePayload() : void
    {
        $client = new StubWebhookClient();
        $body = str_repeat( 'x', 256 * 1024 );

        $this->assertSame( 204, $client->send( $this->model(), 'page.published', 'delivery-id', $body ) );
        $this->assertSame( $body, $client->options[CURLOPT_POSTFIELDS] );
    }


    private function model( string $tenant = 'test' ) : Webhook
    {
        $webhook = new Webhook();
        $webhook->forceFill( [
            'tenant_id' => $tenant,
            'url' => 'https://example.com/hooks/cms',
            'secret' => 'test-secret',
        ] );

        return $webhook;
    }
}

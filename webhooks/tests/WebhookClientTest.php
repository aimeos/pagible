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


class WebhookClientTest extends WebhookTestAbstract
{
    public function testCanonicalUrl() : void
    {
        $client = app( WebhookClient::class );

        $this->assertSame( 'https://example.com/hook?source=cms',
            $client->canonical( 'HTTPS://EXAMPLE.COM/hook?source=cms' ) );
        $this->assertSame( 'https://example.com/a%25zz?b=%25', $client->canonical( 'https://example.com/a%zz?b=%' ) );
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
        yield 'empty' => [''];
        yield 'credentials' => ['https://user:pass@example.com/hook'];
        yield 'fragment' => ['https://example.com/hook#token'];
        yield 'loopback' => ['https://127.0.0.1/hook'];
        yield 'private' => ['https://10.0.0.1/hook'];
        yield 'link local' => ['https://169.254.169.254/latest/meta-data'];
        yield 'shared address space' => ['https://100.100.100.200/hook'];
        yield 'benchmark address space' => ['https://198.18.0.1/hook'];
        yield 'single label' => ['https://localhost/hook'];
        yield 'http default' => ['http://example.com/hook'];
        yield 'nonstandard port' => ['https://example.com:8443/hook'];
        yield 'port 0' => ['https://example.com:0/hook'];
        yield 'port too large' => ['https://example.com:65536/hook'];
        yield 'encoded authority' => ['https://example.com%40internal/hook'];
        yield 'missing authority' => ['https:example.com/hook'];
        yield 'trailing dot' => ['https://example.com./hook'];
        yield 'non-ASCII host' => ['https://exämple.com/hook'];
        yield 'bracketed hostname' => ['https://[example.com]/hook'];
        yield 'bracketed IPv4' => ['https://[93.184.216.34]/hook'];
    }


    #[DataProvider( 'internalUrls' )]
    public function testEndpointAllowsInternalDestination( string $url, string $expected ) : void
    {
        $this->assertSame( $expected, app( WebhookClient::class )->canonical( $url, true ) );
    }


    /**
     * @return iterable<string, array{string, string}>
     */
    public static function internalUrls() : iterable
    {
        yield 'http with port' => ['HTTP://Indexer:8080/hook', 'http://indexer:8080/hook'];
        yield 'localhost' => ['http://localhost/hook', 'http://localhost/hook'];
        yield 'underscore' => ['http://Search_Indexer:8080/hook', 'http://search_indexer:8080/hook'];
        yield 'loopback' => ['http://127.0.0.1:9000/hook', 'http://127.0.0.1:9000/hook'];
        yield 'loopback IPv6' => ['http://[::1]:9000/hook', 'http://[::1]:9000/hook'];
        yield 'private' => ['https://10.0.0.5/hook', 'https://10.0.0.5/hook'];
        yield 'public' => ['https://example.com/hook', 'https://example.com/hook'];
    }


    #[DataProvider( 'deniedInternalUrls' )]
    public function testEndpointRejectsDeniedDestination( string $url ) : void
    {
        $this->expectException( WebhookException::class );
        app( WebhookClient::class )->canonical( $url, true );
    }


    /**
     * @return iterable<string, array{string}>
     */
    public static function deniedInternalUrls() : iterable
    {
        yield 'metadata' => ['http://169.254.169.254/latest/meta-data'];
        yield 'link local IPv6' => ['http://[fe80::1]/hook'];
        yield 'unspecified' => ['http://0.0.0.0/hook'];
        yield 'mapped IPv4' => ['http://[::ffff:127.0.0.1]/hook'];
        yield 'multicast' => ['http://224.0.0.1/hook'];
        yield 'other scheme' => ['ftp://indexer/hook'];
        yield 'credentials' => ['http://user:pass@indexer/hook'];
    }


    public function testDenyCidrsApplyToEndpoints() : void
    {
        config( ['cms.webhooks.deny_cidrs' => ['10.0.0.0/8', '127.0.0.0/8']] );

        try {
            foreach( ['http://10.1.2.3/hook', 'http://127.0.0.1/hook'] as $url ) {
                try {
                    app( WebhookClient::class )->canonical( $url, true );
                    $this->fail( 'Expected a denied destination for ' . $url );
                } catch( WebhookException $e ) {
                    $this->assertSame( 'destination_not_allowed', $e->reason );
                }
            }
        } finally {
            config( ['cms.webhooks.deny_cidrs' => []] );
        }
    }


    public function testDenyListAcceptsSingleAddresses() : void
    {
        $client = app( WebhookClient::class );
        config( ['cms.webhooks.deny_cidrs' => ['10.0.0.5', 'fd00::5']] );

        try {
            $this->assertNull( $client->policyError() );
            $this->assertSame( 'http://10.0.0.6/hook', $client->canonical( 'http://10.0.0.6/hook', true ) );

            foreach( ['http://10.0.0.5/hook', 'http://[fd00::5]/hook'] as $url ) {
                try {
                    $client->canonical( $url, true );
                    $this->fail( 'Expected a denied destination for ' . $url );
                } catch( WebhookException $e ) {
                    $this->assertSame( 'destination_not_allowed', $e->reason );
                }
            }
        } finally {
            config( ['cms.webhooks.deny_cidrs' => []] );
        }
    }


    public function testInvalidOperatorPolicyFailsValidation() : void
    {
        $client = app( WebhookClient::class );
        $this->assertNull( $client->policyError() );

        foreach( [['not-a-cidr'], ['10.0.0.0/33'], ['10.0.0.0/'], ['10.0.0.0/-1'], ['10.0.0'], [42]] as $cidrs )
        {
            config( ['cms.webhooks.deny_cidrs' => $cidrs] );

            try {
                $this->assertSame( 'invalid_policy', $client->policyError() );
            } finally {
                config( ['cms.webhooks.deny_cidrs' => []] );
            }
        }
    }


    public function testInvalidOperatorPolicyBlocksAllDeliveries() : void
    {
        $client = new StubWebhookClient();
        $client->addresses = ['93.184.216.34'];
        config( ['cms.webhooks.deny_cidrs' => ['not-a-cidr']] );

        try {
            $calls = [
                'subscription' => fn() => $client->send( $this->model()->target(), 'delivery-id', '{}' ),
                'endpoint' => fn() => $client->send(
                    ['url' => 'http://indexer:8080/hook', 'secrets' => ['secret'], 'internal' => true],
                    'delivery-id', '{}',
                ),
                'canonical' => fn() => $client->canonical( 'http://10.0.0.1/hook', true ),
            ];

            foreach( $calls as $name => $call )
            {
                try {
                    $call();
                    $this->fail( 'Expected blocked delivery for ' . $name );
                } catch( WebhookException $e ) {
                    $this->assertSame( 'invalid_policy', $e->reason );
                }
            }
        } finally {
            config( ['cms.webhooks.deny_cidrs' => []] );
        }

        $this->assertSame( [], $client->options );
    }


    public function testUnresolvedOrDeniedHostIsntSent() : void
    {
        $client = new StubWebhookClient();
        $endpoint = ['url' => 'http://indexer:8080/hook', 'secrets' => [self::secret( 'endpoint' )], 'internal' => true];

        foreach( ['resolution_failed' => [], 'destination_not_allowed' => ['169.254.169.254']] as $reason => $addresses )
        {
            $client->addresses = $addresses;

            try {
                $client->send( $endpoint, 'delivery-id', '{}' );
                $this->fail( 'Expected ' . $reason );
            } catch( WebhookException $e ) {
                $this->assertSame( $reason, $e->reason );
            }
        }

        $this->assertSame( [], $client->options );
    }


    public function testQueriesEachRecordTypeSeparately() : void
    {
        $client = $this->resolver();
        $ipv6 = '2606:2800:220:1:248:1893:25c8:1946';

        // A failed query for one record type doesn't hide the addresses of the other one
        $client->records = [DNS_A => [['ip' => '93.184.216.34']]];
        $this->assertSame( ['93.184.216.34'], $client->lookup( 'example.com' ) );

        $client->records = [DNS_A => [], DNS_AAAA => [['ipv6' => $ipv6]]];
        $this->assertSame( [$ipv6], $client->lookup( 'example.com' ) );

        $client->records = [DNS_A => [['ip' => '93.184.216.34']], DNS_AAAA => [['ipv6' => $ipv6]]];
        $this->assertSame( ['93.184.216.34', $ipv6], $client->lookup( 'example.com' ) );

        $client->records = [];
        $this->assertSame( [], $client->lookup( 'example.com' ) );

        $this->assertSame( array_merge( ...array_fill( 0, 4, [DNS_A, DNS_AAAA] ) ), $client->types );
    }


    public function testSlowResolutionShortensTheRequest() : void
    {
        $client = new StubWebhookClient();
        Carbon::setTestNow( '2026-09-15 12:00:00 UTC' );

        try {
            // The default budget is the connect timeout, the timeout and the time reserved for resolving
            $client->send( $this->model()->target(), 'delivery-id', '{}' );
            $this->assertSame( 3, $client->options[CURLOPT_CONNECTTIMEOUT] );
            $this->assertSame( 10000, $client->options[CURLOPT_TIMEOUT_MS] );

            $client->resolveTime = 9500;
            $client->send( $this->model()->target(), 'delivery-id', '{}' );
            $this->assertSame( 8500, $client->options[CURLOPT_TIMEOUT_MS] );

            $client->resolveTime = 4000;
            $client->send( $this->model()->target(), 'delivery-id', '{}', now()->getTimestampMs() + 8000 );
            $this->assertSame( 4000, $client->options[CURLOPT_TIMEOUT_MS] );
        } finally {
            Carbon::setTestNow();
        }
    }


    public function testTooLittleTimeLeftFailsWithTimeout() : void
    {
        $client = new StubWebhookClient();
        $client->resolveTime = 5001;
        Carbon::setTestNow( '2026-09-15 12:00:00 UTC' );

        try {
            // Less than the connect timeout is left
            $client->send( $this->model()->target(), 'delivery-id', '{}', now()->getTimestampMs() + 8000 );
            $this->fail( 'Expected timeout' );
        } catch( WebhookException $e ) {
            $this->assertSame( 'timeout', $e->reason );
        } finally {
            Carbon::setTestNow();
        }

        $this->assertSame( [], $client->options );
    }


    public function testBuildsSignedPinnedRequest() : void
    {
        $client = new StubWebhookClient();
        $webhook = $this->model();
        $body = '{"event":"page.published"}';
        Carbon::setTestNow( '2026-09-14 12:00:00 UTC' );

        try {
            $this->assertSame( 204, $client->send( $webhook->target(), 'delivery-id', $body )->status );
        } finally {
            Carbon::setTestNow();
        }

        $headers = $client->options[CURLOPT_HTTPHEADER];

        $this->assertSame( 'https://example.com/hooks/cms', $client->options[CURLOPT_URL] );
        $this->assertSame( $body, $client->options[CURLOPT_POSTFIELDS] );
        $this->assertContains( 'Content-Type: application/json', $headers );
        $this->assertContains( 'Expect:', $headers );
        $this->assertContains( 'User-Agent: Pagible-Webhook/1.0', $headers );
        // The event and tenant are only sent in the signed body
        $this->assertSame( [
            'webhook-id: delivery-id',
            'webhook-timestamp: 1789387200',
            'webhook-signature: ' . $this->signature( 'test', 'delivery-id.1789387200.' . $body ),
        ], array_values( preg_grep( '/^(webhook|x-cms)-/i', $headers ) ?: [] ) );
        $this->assertSame( ['example.com:443:93.184.216.34'], $client->options[CURLOPT_RESOLVE] );
        $this->assertSame( '', $client->options[CURLOPT_PROXY] );
        $this->assertFalse( $client->options[CURLOPT_FOLLOWLOCATION] );
        $this->assertTrue( $client->options[CURLOPT_SSL_VERIFYPEER] );
        $this->assertSame( 2, $client->options[CURLOPT_SSL_VERIFYHOST] );
        $this->assertSame( CURL_SSLVERSION_TLSv1_2, $client->options[CURLOPT_SSLVERSION] );
    }


    public function testSignsWithCurrentAndPreviousSecretDuringGracePeriod() : void
    {
        $client = new StubWebhookClient();
        $webhook = $this->model()->forceFill( [
            'secrets' => self::secrets( 'new', ['old' => Carbon::parse( '2026-09-14 12:00:01 UTC' )] ),
        ] );
        Carbon::setTestNow( '2026-09-14 12:00:00 UTC' );

        try {
            $client->send( $webhook->target(), 'delivery-id', '{}' );
            $current = $client->options[CURLOPT_HTTPHEADER];

            Carbon::setTestNow( '2026-09-14 12:00:01 UTC' );
            $client->send( $webhook->target(), 'delivery-id', '{}' );
            $expired = $client->options[CURLOPT_HTTPHEADER];
        } finally {
            Carbon::setTestNow();
        }

        $this->assertContains( 'webhook-signature: ' . $this->signature( 'new', 'delivery-id.1789387200.{}' )
            . ' ' . $this->signature( 'old', 'delivery-id.1789387200.{}' ), $current );
        $this->assertContains( 'webhook-signature: ' . $this->signature( 'new', 'delivery-id.1789387201.{}' ), $expired );
    }


    public function testSignsLikeStandardWebhooks() : void
    {
        $client = new StubWebhookClient();
        $webhook = $this->model()->forceFill( ['secrets' => [['secret' => 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw', 'until' => null]]] );
        Carbon::setTestNow( Carbon::createFromTimestamp( 1614265330 ) );

        try {
            $client->send( $webhook->target(), 'msg_p5jXN8AQM9LWM0D4loKWxJek', '{"test": 2432232314}' );
        } finally {
            Carbon::setTestNow();
        }

        // Example from the Standard Webhooks specification, which the receiver libraries verify
        $this->assertContains( 'webhook-signature: v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE=', $client->options[CURLOPT_HTTPHEADER] );
    }


    #[DataProvider( 'invalidSecrets' )]
    public function testInvalidSecretIsntSent( string $secret ) : void
    {
        $client = new StubWebhookClient();

        try {
            $client->send( $this->model()->forceFill( ['secrets' => [['secret' => $secret, 'until' => null]]] )->target(), 'delivery-id', '{}' );
            $this->fail( 'Invalid secret was used' );
        } catch( WebhookException $e ) {
            $this->assertSame( 'invalid_secret', $e->reason );
        }

        $this->assertSame( [], $client->options );
    }


    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidSecrets() : iterable
    {
        yield 'no prefix' => [base64_encode( str_repeat( 's', 32 ) )];
        yield 'short key' => ['whsec_' . base64_encode( str_repeat( 's', 23 ) )];
        yield 'url-safe base64' => ['whsec_' . str_repeat( '-_', 16 )];
        yield 'whitespace' => ['whsec_' . base64_encode( str_repeat( 's', 32 ) ) . "\n"];
    }


    public function testPinsAllAllowedAddresses() : void
    {
        $client = new StubWebhookClient();
        $client->addresses = ['93.184.216.34', '10.0.0.7', '2606:2800:220:1:248:1893:25c8:1946', '93.184.216.34'];

        $client->send( $this->model()->target(), 'delivery-id', '{}' );

        $this->assertSame( ['example.com:443:93.184.216.34,[2606:2800:220:1:248:1893:25c8:1946]'], $client->options[CURLOPT_RESOLVE] );
    }


    public function testOnlyEndpointsAskTheSystemResolver() : void
    {
        $client = new StubWebhookClient();
        $endpoint = ['url' => 'https://indexer.example/hook', 'secrets' => [self::secret( 'endpoint' )], 'internal' => true];

        $client->send( $this->model()->target(), 'delivery-id', '{}' );
        $client->send( $endpoint, 'delivery-id', '{}' );

        $this->assertSame( [['example.com', false], ['indexer.example', true]], $client->lookups );
    }


    public function testBuildsEndpointRequest() : void
    {
        $client = new StubWebhookClient();
        $client->addresses = ['169.254.169.254', '10.0.0.7'];
        $endpoint = ['url' => 'https://indexer:8443/hook', 'secrets' => [self::secret( 'endpoint' ), self::secret( 'previous' )], 'internal' => true];
        Carbon::setTestNow( '2026-09-14 12:00:00 UTC' );

        try {
            $this->assertSame( 204, $client->send( $endpoint, 'delivery-id', '{}' )->status );
        } finally {
            Carbon::setTestNow();
        }

        $headers = $client->options[CURLOPT_HTTPHEADER];

        $this->assertSame( 'https://indexer:8443/hook', $client->options[CURLOPT_URL] );
        $this->assertSame( ['indexer:8443:10.0.0.7'], $client->options[CURLOPT_RESOLVE] );
        $this->assertFalse( $client->options[CURLOPT_FOLLOWLOCATION] );
        $this->assertContains( 'webhook-signature: ' . $this->signature( 'endpoint', 'delivery-id.1789387200.{}' )
            . ' ' . $this->signature( 'previous', 'delivery-id.1789387200.{}' ), $headers );
    }


    public function testEndpointMayResolveToLoopbackOverHttp() : void
    {
        $client = new StubWebhookClient();
        $client->addresses = ['127.0.0.1'];
        $endpoint = ['url' => 'http://indexer:8080/hook', 'secrets' => [self::secret( 'endpoint' )], 'internal' => true];

        $this->assertSame( 204, $client->send( $endpoint, 'delivery-id', '{}' )->status );
        $this->assertSame( ['indexer:8080:127.0.0.1'], $client->options[CURLOPT_RESOLVE] );
    }


    #[DataProvider( 'deniedAddresses' )]
    public function testSubscriptionRejectsNonPublicResolvedAddress( string $address ) : void
    {
        $client = new StubWebhookClient();
        $client->addresses = [$address];

        try {
            $client->send( $this->model()->target(), 'delivery-id', '{}' );
            $this->fail( 'Expected a denied destination.' );
        } catch( WebhookException $e ) {
            $this->assertSame( 'destination_not_allowed', $e->reason );
            $this->assertSame( [], $client->options );
        }
    }


    /**
     * @return iterable<string, array{string}>
     */
    public static function deniedAddresses() : iterable
    {
        yield 'loopback' => ['127.0.0.1'];
        yield 'private' => ['10.0.0.7'];
        yield 'metadata' => ['169.254.169.254'];
    }


    public function testEndpointRejectsLinkLocalResolvedAddress() : void
    {
        $client = new StubWebhookClient();
        $client->addresses = ['169.254.169.254'];
        $endpoint = ['url' => 'http://indexer/hook', 'secrets' => [self::secret( 'endpoint' )], 'internal' => true];

        $this->expectException( WebhookException::class );
        $client->send( $endpoint, 'delivery-id', '{}' );
    }


    public function testResponseBodyIsNotRead() : void
    {
        $client = new StubWebhookClient();
        $client->overflow = 'body';

        $this->assertSame( 204, $client->send( $this->model()->target(), 'delivery-id', '{}' )->status );
        $this->assertSame( 0, call_user_func( $client->options[CURLOPT_WRITEFUNCTION], null, '{}' ) );

        $client->status = 503;
        $this->assertTrue( $client->send( $this->model()->target(), 'delivery-id', '{}' )->retryable() );
    }


    public function testRejectsOversizedResponseHeaders() : void
    {
        $client = new StubWebhookClient();
        $client->overflow = 'headers';

        try {
            $client->send( $this->model()->target(), 'delivery-id', '{}' );
            $this->fail( 'Expected an oversized response exception.' );
        } catch( WebhookException $e ) {
            $this->assertSame( 'response_headers_too_large', $e->reason );
        }
    }


    #[DataProvider( 'transportErrors' )]
    public function testReportsSpecificTransportFailure( int $errno, string $reason ) : void
    {
        $client = new StubWebhookClient();
        $client->errno = $errno;

        try {
            $client->send( $this->model()->target(), 'delivery-id', '{}' );
            $this->fail( 'Expected a transport exception.' );
        } catch( WebhookException $e ) {
            $this->assertSame( $reason, $e->reason );
        }
    }


    /**
     * @return iterable<string, array{int, string}>
     */
    public static function transportErrors() : iterable
    {
        yield 'connection refused' => [7, 'connection_failed'];
        yield 'timeout' => [28, 'timeout'];
        yield 'certificate' => [60, 'transport_error'];
        yield 'other' => [56, 'transport_error'];
    }


    /**
     * @param list<string> $headers
     */
    #[DataProvider( 'retryAfterHeaders' )]
    public function testReturnsRetryAfter( array $headers, int $expected ) : void
    {
        $client = new StubWebhookClient();
        $client->headers = $headers;
        $client->status = 503;

        $response = $client->send( $this->model()->target(), 'delivery-id', '{}' );

        $this->assertSame( 503, $response->status );
        $this->assertSame( $expected, $response->retryAfter );
        $this->assertTrue( $response->retryable() );
    }


    /**
     * @return iterable<string, array{list<string>, int}>
     */
    public static function retryAfterHeaders() : iterable
    {
        yield 'none' => [['HTTP/1.1 503 Service Unavailable'], 0];
        yield 'seconds' => [['HTTP/1.1 503 Service Unavailable', 'Retry-After: 120'], 120];
        yield 'lower case' => [['HTTP/1.1 503 Service Unavailable', 'retry-after:  90 '], 90];
        yield 'http date' => [['HTTP/1.1 503 Service Unavailable', 'Retry-After: Mon, 14 Sep 2026 12:10:00 GMT'], 0];
        yield 'invalid' => [['HTTP/1.1 503 Service Unavailable', 'Retry-After: soon'], 0];
        yield 'huge' => [['HTTP/1.1 503 Service Unavailable', 'Retry-After: 99999999999999999999'], PHP_INT_MAX];
        yield 'interim response' => [['HTTP/1.1 100 Continue', 'Retry-After: 120', '', 'HTTP/1.1 503 Service Unavailable'], 0];
    }


    public function testSendsLargePayload() : void
    {
        $client = new StubWebhookClient();
        $body = str_repeat( 'x', 256 * 1024 );

        $this->assertSame( 204, $client->send( $this->model()->target(), 'delivery-id', $body )->status );
        $this->assertSame( $body, $client->options[CURLOPT_POSTFIELDS] );
    }


    private function model() : Webhook
    {
        $webhook = new Webhook();
        $webhook->forceFill( [
            'tenant_id' => 'test',
            'url' => 'https://example.com/hooks/cms',
            'secrets' => self::secrets(),
        ] );

        return $webhook;
    }


    /**
     * Returns the signature a receiver expects for the secret with the given name, see secret().
     */
    private function signature( string $name, string $content ) : string
    {
        $key = base64_decode( substr( self::secret( $name ), 6 ) );

        return 'v1,' . base64_encode( hash_hmac( 'sha256', $content, $key, true ) );
    }


    /**
     * Returns a client which answers DNS queries from its records, other queries fail.
     */
    private function resolver() : WebhookClient
    {
        return new class extends WebhookClient {
            /** @var array<int, list<array<string, mixed>>> */
            public array $records = [];
            /** @var list<int> */
            public array $types = [];
            public function lookup( string $host, bool $system = false ) : array
            {
                return parent::lookup( $host, $system );
            }
            protected function records( string $host, int $type ) : array
            {
                $this->types[] = $type;
                return $this->records[$type] ?? [];
            }
        };
    }
}

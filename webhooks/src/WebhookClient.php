<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use GuzzleHttp\Psr7\Uri;
use Symfony\Component\HttpFoundation\IpUtils;


/**
 * Canonicalizes and delivers signed webhook requests through a pinned cURL connection.
 */
class WebhookClient
{
    /** Seconds to connect to the destination */
    private const CONNECT_TIMEOUT = 3;

    /** Seconds reserved for resolving the host name, which isn't limited by the connect timeout */
    private const RESOLVE_TIME = 5;

    /** Bytes of the response headers which are accepted, cURL before 8.3 stores all of them without a limit */
    private const MAX_HEADERS = 32768;

    /** @var list<string> */
    private const HARD_DENY = [
        '0.0.0.0/8', '169.254.0.0/16', '224.0.0.0/4', '240.0.0.0/4',
        '::/128', '::ffff:0:0/96', '64:ff9b::/96', '64:ff9b:1::/48',
        '2001::/32', '2002::/16', 'fe80::/10', 'ff00::/8',
    ];

    /** @var array<int, string> Failure reasons for cURL error codes, others are "transport_error" */
    private const ERRORS = [7 => 'connection_failed', 28 => 'timeout'];


    /**
     * Returns the seconds one request may take at most, including resolving the host name.
     */
    public static function duration() : int
    {
        return array_sum( self::timeouts() ) + self::RESOLVE_TIME;
    }


    /**
     * Returns the canonical endpoint URL.
     *
     * Subscriptions are limited to public HTTPS hosts on port 443. Operator-defined endpoints
     * ($internal = true) may also use HTTP, other ports, internal host names, private and loopback
     * addresses.
     */
    public function canonical( string $url, bool $internal = false ) : string
    {
        if( preg_match( '/[\x00-\x20\x7f\\\\]/', $url ) || str_contains( $url, '#' ) ) {
            throw new WebhookException( 'invalid_url' );
        }

        try {
            $uri = new Uri( $url );
        } catch( \Throwable ) {
            throw new WebhookException( 'invalid_url' );
        }

        // Scheme and host are lower case, default ports are removed and a lone "%" is encoded.
        // Ports above 65535 are rejected by the parser, port 0 isn't
        $scheme = $uri->getScheme();
        $host = $this->normalizeHost( $uri->getHost() );
        $port = $uri->getPort();

        if( !in_array( $scheme, ['http', 'https'], true )
            || $uri->getUserInfo() !== '' || !$this->validHost( $host )
            || $port === 0
        ) {
            throw new WebhookException( 'invalid_url' );
        }

        $canonical = (string) $uri;

        if( strlen( $canonical ) > 500 ) {
            throw new WebhookException( 'invalid_url' );
        }

        $ip = filter_var( $host, FILTER_VALIDATE_IP ) !== false;

        if( !$internal && ( $scheme !== 'https' || ( $port ?? 443 ) !== 443 || ( !$ip && !str_contains( $host, '.' ) ) )
            || ( $ip && !$this->allowedIp( $host, $internal, $this->denied() ) )
        ) {
            throw new WebhookException( 'destination_not_allowed' );
        }

        return $canonical;
    }


    /**
     * Returns the signing key of a Standard Webhooks secret or NULL if the secret is invalid.
     *
     * Secrets are "whsec_" and at least 24 random bytes in standard base64 encoding, which the
     * receiver libraries of all languages accept.
     */
    public function key( string $secret ) : ?string
    {
        if( !preg_match( '#^whsec_([A-Za-z0-9+/]+={0,2})\z#', $secret, $match ) ) {
            return null;
        }

        $key = base64_decode( $match[1], true );

        return is_string( $key ) && strlen( $key ) >= 24 ? $key : null;
    }


    /**
     * Returns why the operator deny list can't be used or NULL if it is valid.
     */
    public function policyError() : ?string
    {
        try {
            $this->denied();
        } catch( WebhookException $e ) {
            return $e->reason;
        }

        return null;
    }


    /**
     * Sends one signed request to a subscription or an operator-defined endpoint through a pinned connection.
     *
     * The request is signed like Standard Webhooks (https://www.standardwebhooks.com), so receivers
     * can verify it with their libraries. The event and tenant are only part of the signed body.
     * The host name resolution can't be interrupted, so the time it took is subtracted from the time
     * left for the request. Without a deadline, the request may take the time reserved for resolving
     * the host name and the configured timeouts.
     *
     * @param array{url: string, secrets: list<string>, internal: bool} $target Destination whose URL was
     *  returned by canonical(), one signature per secret, receivers accept any matching one. Only
     *  operator-defined endpoints are internal
     * @param int|null $deadline Unix time in milliseconds when the request must be finished
     * @throws WebhookException With the "timeout" reason if less than the connect timeout is left
     */
    public function send( array $target, string $deliveryId, string $body, ?int $deadline = null ) : WebhookResponse
    {
        [$connect, $timeout] = self::timeouts();
        $deadline ??= now()->getTimestampMs() + self::duration() * 1000;

        $uri = new Uri( $target['url'] );
        $host = $this->normalizeHost( $uri->getHost() );
        $port = $uri->getPort() ?? ( $uri->getScheme() === 'https' ? 443 : 80 );
        $addresses = $this->resolve( $host, $target['internal'] );

        // The request mustn't outlast the caller, e.g. the queue worker aborts a job without recording
        // the failure, so it's neither retried later nor pauses the destination
        if( ( $left = $deadline - now()->getTimestampMs() ) < $connect * 1000 ) {
            throw new WebhookException( 'timeout' );
        }

        $timestamp = (string) now()->timestamp;
        $signed = $deliveryId . '.' . $timestamp . '.' . $body;
        $signature = implode( ' ', array_map(
            fn( string $secret ) => 'v1,' . base64_encode( hash_hmac( 'sha256', $signed,
                $this->key( $secret ) ?? throw new WebhookException( 'invalid_secret' ), true ) ),
            $target['secrets'],
        ) );
        $headers = [
            'Content-Type: application/json',
            'Expect:',
            'User-Agent: Pagible-Webhook/1.0',
            'webhook-id: ' . $deliveryId,
            'webhook-timestamp: ' . $timestamp,
            'webhook-signature: ' . $signature,
        ];
        $headerBytes = 0;
        $headersExceeded = false;
        $bodyStarted = false;
        $retryAfter = 0;

        $options = [
            CURLOPT_URL => $target['url'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => $connect,
            CURLOPT_TIMEOUT_MS => min( $timeout * 1000, $left ),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_PROXY => '',
            CURLOPT_HEADERFUNCTION => function( $curl, string $line ) use (
                &$headerBytes, &$headersExceeded, &$retryAfter
            ) : int {
                $headerBytes += strlen( $line );

                if( $headerBytes > self::MAX_HEADERS ) {
                    $headersExceeded = true;
                    return 0;
                }

                if( str_starts_with( $line, 'HTTP/' ) ) {
                    $retryAfter = 0; // headers of a new response
                } elseif( strncasecmp( $line, 'retry-after:', 12 ) === 0 ) {
                    $retryAfter = $this->retryAfter( substr( $line, 12 ) );
                }

                return strlen( $line );
            },
            // Only the HTTP status counts, so the transfer stops when the body arrives
            CURLOPT_WRITEFUNCTION => function( $curl, string $chunk ) use ( &$bodyStarted ) : int {
                $bodyStarted = true;
                return 0;
            },
        ];

        // Pins all allowed addresses so cURL can try the next one if a server is down
        if( !filter_var( $host, FILTER_VALIDATE_IP ) )
        {
            $resolved = array_map( fn( string $ip ) => str_contains( $ip, ':' ) ? '[' . $ip . ']' : $ip, $addresses );
            $options[CURLOPT_RESOLVE] = [$host . ':' . $port . ':' . implode( ',', $resolved )];
        }

        [$success, $status, $errno] = $this->execute( $options );

        // The status is known before the body arrives
        if( $success || $bodyStarted && $status > 0 ) {
            return new WebhookResponse( $status, $retryAfter );
        }

        throw new WebhookException( $headersExceeded ? 'response_headers_too_large' : ( self::ERRORS[$errno] ?? 'transport_error' ) );
    }


    /**
     * Executes prepared cURL options behind a narrow test seam.
     *
     * @param array<int, mixed> $options
     * @return array{bool, int, int} Success, HTTP response status and cURL error code
     */
    protected function execute( array $options ) : array
    {
        $curl = curl_init() ?: throw new WebhookException( 'transport_error' );

        // The handle is closed when it goes out of scope, curl_close() is deprecated since PHP 8.5
        curl_setopt_array( $curl, $options );
        $success = curl_exec( $curl );

        return [$success !== false, (int) curl_getinfo( $curl, CURLINFO_RESPONSE_CODE ), curl_errno( $curl )];
    }


    /**
     * Returns the addresses of a host name behind a narrow test seam.
     *
     * @param bool $system Also ask the system resolver if DNS doesn't know the host name
     * @return list<string>
     */
    protected function lookup( string $host, bool $system = false ) : array
    {
        $addresses = [];

        // Queried separately because a failed query for one record type fails a combined lookup
        foreach( [DNS_A => 'ip', DNS_AAAA => 'ipv6'] as $type => $key )
        {
            foreach( $this->records( $host, $type ) as $record )
            {
                if( is_string( $ip = $record[$key] ?? null ) ) {
                    $addresses[] = $ip;
                }
            }
        }

        // Internal names may only be known to the system resolver, e.g. from /etc/hosts. Public
        // host names are never asked twice so failed lookups don't take twice as long
        return $addresses || !$system
            ? array_slice( $addresses, 0, 16 )
            : array_slice( @gethostbynamel( $host ) ?: [], 0, 16 );
    }


    /**
     * Returns the DNS records of one type behind a narrow test seam.
     *
     * @return list<array<string, mixed>> Records, none if the query failed
     */
    protected function records( string $host, int $type ) : array
    {
        $records = @dns_get_record( $host, $type );

        return is_array( $records ) ? $records : [];
    }


    /**
     * Operator denials and hard denials always win, only operator endpoints may reach internal addresses.
     *
     * @param list<string> $denied Validated operator denials
     */
    private function allowedIp( string $ip, bool $internal, array $denied ) : bool
    {
        if( !filter_var( $ip, FILTER_VALIDATE_IP ) || IpUtils::checkIp( $ip, [...$denied, ...self::HARD_DENY] ) ) {
            return false;
        }

        // Loopback, private and reserved addresses aren't in the global range
        return $internal || filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE ) !== false;
    }


    /**
     * Returns the operator denials.
     *
     * An invalid deny list must not allow the ranges it was meant to block, so it blocks all deliveries.
     *
     * @return list<string>
     * @throws WebhookException If the deny list is invalid
     */
    private function denied() : array
    {
        $denied = [];

        foreach( (array) config( 'cms.webhooks.deny_cidrs', [] ) as $cidr )
        {
            if( !is_string( $cidr ) || !$this->validCidr( $cidr ) ) {
                throw new WebhookException( 'invalid_policy' );
            }

            $denied[] = $cidr;
        }

        return $denied;
    }


    /**
     * Removes URI brackets from IPv6 literals for policy checks and DNS handling.
     */
    private function normalizeHost( string $host ) : string
    {
        if( str_starts_with( $host, '[' ) && str_ends_with( $host, ']' ) ) {
            $literal = substr( $host, 1, -1 );

            if( filter_var( $literal, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
                return $literal;
            }
        }

        return $host;
    }


    /**
     * Resolves the host and returns the allowed addresses, denied ones are skipped.
     *
     * Only operator endpoints ($internal = true) ask the system resolver for names unknown to DNS.
     *
     * @return non-empty-list<string>
     */
    private function resolve( string $host, bool $internal ) : array
    {
        $denied = $this->denied();
        $addresses = filter_var( $host, FILTER_VALIDATE_IP ) ? [$host] : $this->lookup( $host, $internal );

        if( $addresses === [] ) {
            throw new WebhookException( 'resolution_failed' );
        }

        $allowed = array_values( array_unique( array_filter(
            $addresses,
            fn( string $ip ) => $this->allowedIp( $ip, $internal, $denied ),
        ) ) );

        if( $allowed === [] ) {
            throw new WebhookException( 'destination_not_allowed' );
        }

        return $allowed;
    }


    /**
     * Returns the seconds from a "Retry-After" header value, HTTP dates are ignored.
     */
    private function retryAfter( string $value ) : int
    {
        $value = trim( $value );

        // Huge values are limited to PHP_INT_MAX
        return ctype_digit( $value ) ? (int) $value : 0;
    }


    /**
     * Returns the connect timeout and the timeout of the whole request in seconds.
     *
     * @return array{int, int}
     */
    private static function timeouts() : array
    {
        return [
            self::CONNECT_TIMEOUT,
            max( 1, (int) config( 'cms.webhooks.timeout', 10 ) ),
        ];
    }


    /**
     * Accepts CIDR ranges and single IP addresses, which deny only that address.
     */
    private function validCidr( string $cidr ) : bool
    {
        [$network, $bits] = array_pad( explode( '/', $cidr, 2 ), 2, null );
        $packed = is_string( $network ) ? inet_pton( $network ) : false;

        if( $packed === false ) {
            return false;
        }

        return $bits === null || ctype_digit( $bits ) && (int) $bits <= strlen( $packed ) * 8;
    }


    private function validHost( string $host ) : bool
    {
        if( filter_var( $host, FILTER_VALIDATE_IP ) ) {
            return true;
        }

        if( preg_match( '/^[0-9.]+$/', $host ) || strlen( $host ) > 253 ) {
            return false;
        }

        foreach( explode( '.', $host ) as $label ) {
            if( $label === '' || strlen( $label ) > 63
                || !preg_match( '/^[a-z0-9_](?:[a-z0-9_-]*[a-z0-9_])?$/i', $label )
            ) {
                return false;
            }
        }

        return true;
    }
}

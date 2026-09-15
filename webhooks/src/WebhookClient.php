<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Models\Webhook;
use GuzzleHttp\Psr7\Uri;
use Symfony\Component\HttpFoundation\IpUtils;


/**
 * Canonicalizes and delivers signed webhook requests through a pinned cURL connection.
 */
class WebhookClient
{
    /** @var list<string> */
    private const HARD_DENY = [
        '0.0.0.0/8', '127.0.0.0/8', '169.254.0.0/16', '224.0.0.0/4', '240.0.0.0/4',
        '::/128', '::1/128', '::ffff:0:0/96', '64:ff9b::/96', '64:ff9b:1::/48',
        '2001::/32', '2002::/16', 'fe80::/10', 'ff00::/8',
    ];


    /**
     * Returns the canonical form stored in the encrypted URL column.
     */
    public function canonical( string $url ) : string
    {
        if( $url === '' || strlen( $url ) > 500
            || preg_match( '/[\x00-\x20\x7f\\\\]/', $url )
            || preg_match( '/%(?![0-9a-f]{2})/i', $url )
            || preg_match( '/%(?:0[0-9a-f]|1[0-9a-f]|7f)/i', $url )
            || str_contains( $url, '#' )
        ) {
            throw new WebhookException( 'invalid_url' );
        }

        if( !preg_match( '~^([a-z][a-z0-9+.-]*)://([^/?#]+)~i', $url, $matches )
            || str_contains( $matches[2], '@' )
            || preg_match( '/%(?:2f|3a|40|5c)/i', $matches[2] )
        ) {
            throw new WebhookException( 'invalid_url' );
        }

        try {
            $uri = new Uri( $url );
        } catch( \Throwable ) {
            throw new WebhookException( 'invalid_url' );
        }

        $scheme = strtolower( $uri->getScheme() );
        $uriHost = strtolower( $uri->getHost() );
        $host = $this->normalizeHost( $uriHost );
        $port = $uri->getPort();

        if( !in_array( $scheme, ['http', 'https'], true )
            || $host === '' || $uri->getUserInfo() !== '' || str_ends_with( $host, '.' )
            || preg_match( '/[^\x21-\x7e]/', $host ) || !$this->validHost( $host )
            || ( $port !== null && ( $port < 1 || $port > 65535 ) )
        ) {
            throw new WebhookException( 'invalid_url' );
        }

        $uri = $uri->withScheme( $scheme )->withHost( $uriHost );
        $canonical = (string) $uri;

        if( strlen( $canonical ) > 500 ) {
            throw new WebhookException( 'invalid_url' );
        }

        $this->assertConfigured( $uri, $host );

        if( filter_var( $host, FILTER_VALIDATE_IP ) && !$this->allowedIp( $host, $host ) ) {
            throw new WebhookException( 'destination_not_allowed' );
        }

        return $canonical;
    }


    /**
     * Verifies all operator destination-policy entries at activation time.
     */
    public function validatePolicy() : void
    {
        foreach( (array) config( 'cms.webhooks.deny_cidrs', [] ) as $cidr ) {
            if( !is_string( $cidr ) || !$this->validCidr( $cidr ) ) {
                throw new \LogicException( 'Invalid CMS webhook deny CIDR configuration.' );
            }
        }

        foreach( (array) config( 'cms.webhooks.hosts', [] ) as $host => $rule )
        {
            if( !is_string( $host ) || strtolower( $host ) !== $host || !$this->validHost( $host )
                || !is_array( $rule ) || array_diff( array_keys( $rule ), ['schemes', 'ports', 'cidrs', 'ca'] ) !== []
            ) {
                throw new \LogicException( 'Invalid CMS webhook host policy.' );
            }

            $schemes = $rule['schemes'] ?? ['https'];
            $ports = $rule['ports'] ?? [443];

            if( !is_array( $schemes ) || $schemes === []
                || array_filter( $schemes, fn( mixed $scheme ) =>
                    !is_string( $scheme ) || !in_array( $scheme, ['http', 'https'], true )
                ) !== []
                || !is_array( $ports ) || $ports === []
                || array_filter( $ports, fn( mixed $port ) =>
                    !is_int( $port ) || $port < 1 || $port > 65535
                ) !== []
            ) {
                throw new \LogicException( 'Invalid CMS webhook scheme or port policy.' );
            }

            foreach( (array) ( $rule['cidrs'] ?? [] ) as $cidr ) {
                if( !is_string( $cidr ) || !$this->validCidr( $cidr ) ) {
                    throw new \LogicException( 'Invalid CMS webhook host CIDR configuration.' );
                }
            }

            if( isset( $rule['ca'] ) && ( !is_string( $rule['ca'] ) || !is_readable( $rule['ca'] ) ) ) {
                throw new \LogicException( 'Invalid CMS webhook CA configuration.' );
            }
        }
    }


    /**
     * Sends one signed request and returns its HTTP status.
     */
    public function send( Webhook $webhook, string $event, string $deliveryId, string $body ) : int
    {
        foreach( [$event, $deliveryId, $webhook->tenant_id] as $header ) {
            if( preg_match( '/[\x00-\x1f\x7f]/', $header ) ) {
                throw new WebhookException( 'invalid_header' );
            }
        }

        $url = $this->canonical( $webhook->url );
        $uri = new Uri( $url );
        $host = $this->normalizeHost( $uri->getHost() );
        $port = $uri->getPort() ?? ( $uri->getScheme() === 'https' ? 443 : 80 );
        $ip = $this->resolve( $host );
        $timestamp = (string) now()->timestamp;
        $signed = implode( "\n", [
            'v2',
            'x-cms-event:' . $event,
            'x-cms-tenant:' . $webhook->tenant_id,
            'x-cms-delivery:' . $deliveryId,
            'x-cms-timestamp:' . $timestamp,
            '',
            $body,
        ] );
        $signature = 'v2=' . hash_hmac( 'sha256', $signed, $webhook->secret );
        $headers = [
            'Accept: application/json',
            'Accept-Encoding: identity',
            'Content-Type: application/json',
            'Expect:',
            'User-Agent: Pagible-Webhook/1.0',
            'X-Cms-Event: ' . $event,
            $webhook->tenant_id === '' ? 'X-Cms-Tenant;' : 'X-Cms-Tenant: ' . $webhook->tenant_id,
            'X-Cms-Delivery: ' . $deliveryId,
            'X-Cms-Timestamp: ' . $timestamp,
            'X-Cms-Signature: ' . $signature,
        ];
        $maxBody = max( 0, (int) config( 'cms.webhooks.http.max_body', 16384 ) );
        $maxHeaders = max( 0, (int) config( 'cms.webhooks.http.max_headers', 32768 ) );
        $bodyBytes = 0;
        $headerBytes = 0;
        $bodyExceeded = false;
        $headersExceeded = false;

        try
        {
            $options = [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_MAXREDIRS => 0,
                CURLOPT_CONNECTTIMEOUT => max( 1, (int) config( 'cms.webhooks.http.connect_timeout', 3 ) ),
                CURLOPT_TIMEOUT => max( 1, (int) config( 'cms.webhooks.http.timeout', 15 ) ),
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                CURLOPT_PROXY => '',
                CURLOPT_NOPROXY => '*',
                CURLOPT_HEADERFUNCTION => function( $curl, string $line ) use (
                    &$headerBytes, &$headersExceeded, $maxHeaders
                ) : int {
                    $headerBytes += strlen( $line );

                    if( $headerBytes > $maxHeaders ) {
                        $headersExceeded = true;
                        return 0;
                    }

                    return strlen( $line );
                },
                CURLOPT_WRITEFUNCTION => function( $curl, string $chunk ) use (
                    &$bodyBytes, &$bodyExceeded, $maxBody
                ) : int {
                    $bodyBytes += strlen( $chunk );

                    if( $bodyBytes > $maxBody ) {
                        $bodyExceeded = true;
                        return 0;
                    }

                    return strlen( $chunk );
                },
            ];

            if( defined( 'CURLOPT_PROTOCOLS_STR' ) ) {
                $options[constant( 'CURLOPT_PROTOCOLS_STR' )] = $uri->getScheme();
            } elseif( defined( 'CURLOPT_PROTOCOLS' ) ) {
                $options[CURLOPT_PROTOCOLS] = $uri->getScheme() === 'https' ? CURLPROTO_HTTPS : CURLPROTO_HTTP;
            }

            if( !filter_var( $host, FILTER_VALIDATE_IP ) ) {
                $resolved = str_contains( $ip, ':' ) ? '[' . $ip . ']' : $ip;
                $options[CURLOPT_RESOLVE] = [$host . ':' . $port . ':' . $resolved];
            }

            $rule = $this->rule( $host );

            if( isset( $rule['ca'] ) && is_string( $rule['ca'] ) ) {
                $options[CURLOPT_CAINFO] = $rule['ca'];
            }

            [$success, $status] = $this->execute( $options );

            if( !$success ) {
                throw new WebhookException(
                    $headersExceeded ? 'response_headers_too_large'
                        : ( $bodyExceeded ? 'response_body_too_large' : 'transport_error' )
                );
            }

            return $status;
        }
        catch( WebhookException $e ) {
            throw $e;
        }
        catch( \Throwable ) {
            throw new WebhookException( 'transport_error' );
        }
    }


    /**
     * Executes prepared cURL options behind a narrow test seam.
     *
     * @param array<int, mixed> $options
     * @return array{bool, int} Success and HTTP response status
     */
    protected function execute( array $options ) : array
    {
        $curl = curl_init();

        if( $curl === false ) {
            throw new WebhookException( 'transport_unavailable' );
        }

        try {
            curl_setopt_array( $curl, $options );
            $success = curl_exec( $curl );
            $status = (int) curl_getinfo( $curl, CURLINFO_RESPONSE_CODE );

            return [$success !== false, $status];
        } finally {
            curl_close( $curl );
        }
    }


    protected function resolve( string $host ) : string
    {
        if( filter_var( $host, FILTER_VALIDATE_IP ) ) {
            if( !$this->allowedIp( $host, $host ) ) {
                throw new WebhookException( 'destination_not_allowed' );
            }

            return $host;
        }

        $addresses = [];

        foreach( array_slice( @dns_get_record( $host, DNS_A | DNS_AAAA ) ?: [], 0, 16 ) as $record ) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;

            if( is_string( $ip ) ) {
                $addresses[$ip] = true;
            }
        }

        if( $addresses === [] ) {
            throw new WebhookException( 'resolution_failed' );
        }

        foreach( array_keys( $addresses ) as $ip ) {
            if( $this->allowedIp( $host, $ip ) ) {
                return $ip;
            }
        }

        throw new WebhookException( 'destination_not_allowed' );
    }


    /**
     * Enforces scheme and port relaxations before an endpoint is stored or sent.
     */
    private function assertConfigured( Uri $uri, string $host ) : void
    {
        $scheme = $uri->getScheme();
        $port = $uri->getPort() ?? ( $scheme === 'https' ? 443 : 80 );
        $rule = $this->rule( $host );
        $schemes = array_values( array_filter( (array) ( $rule['schemes'] ?? ['https'] ), 'is_string' ) );
        $ports = array_map( 'intval', (array) ( $rule['ports'] ?? [443] ) );

        if( ( !filter_var( $host, FILTER_VALIDATE_IP ) && !str_contains( $host, '.' ) && $rule === [] )
            || !in_array( $scheme, $schemes, true ) || !in_array( $port, $ports, true )
        ) {
            throw new WebhookException( 'destination_not_allowed' );
        }
    }


    private function allowedIp( string $host, string $ip ) : bool
    {
        if( !filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            return false;
        }

        $denied = array_values( array_filter(
            (array) config( 'cms.webhooks.deny_cidrs', [] ), 'is_string'
        ) );

        if( IpUtils::checkIp( $ip, [...$denied, ...self::HARD_DENY] ) ) {
            return false;
        }

        if( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE ) ) {
            return true;
        }

        $allowed = array_values( array_filter(
            (array) ( $this->rule( $host )['cidrs'] ?? [] ), 'is_string'
        ) );

        return IpUtils::checkIp( $ip, $allowed );
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
     * @return array<string, mixed>
     */
    private function rule( string $host ) : array
    {
        $rules = (array) config( 'cms.webhooks.hosts', [] );
        $rule = $rules[$host] ?? [];

        return is_array( $rule ) ? $rule : [];
    }


    private function validCidr( string $cidr ) : bool
    {
        [$network, $bits] = array_pad( explode( '/', $cidr, 2 ), 2, null );
        $packed = is_string( $network ) ? inet_pton( $network ) : false;

        if( $packed === false || $bits === null || !ctype_digit( $bits ) ) {
            return false;
        }

        return (int) $bits >= 0 && (int) $bits <= strlen( $packed ) * 8;
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
                || !preg_match( '/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/i', $label )
            ) {
                return false;
            }
        }

        return true;
    }
}

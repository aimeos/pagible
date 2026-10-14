<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;


/**
 * HTTP client mitigating SSRF by validating and pinning the resolved IP of every request and redirect.
 */
class SafeHttp
{
    /**
     * Sends a request while resolving and pinning every redirect target.
     *
     * @param string $url Initial http(s) URL
     * @param array<string, mixed> $options Additional safe HTTP client options
     * @param array<string, string> $headers Request headers
     * @param string $method HTTP method, e.g. "GET" or "HEAD"
     * @return Response Final response, including unsuccessful non-redirect responses
     */
    public static function request( string $url, array $options = [], array $headers = [], string $method = 'GET' ) : Response
    {
        for( $redirects = 0; ; $redirects++ )
        {
            $pending = Http::withHeaders( $headers )->withOptions( self::options( $url ) + $options );

            // Guzzle's stream handler ignores the "curl" options including the IP pin
            if( !empty( $options['stream'] ) ) {
                $pending->setHandler( new HttpStream );
            }

            $response = $pending->send( $method, $url );

            if( !in_array( $response->status(), [301, 302, 303, 307, 308], true ) ) {
                return $response;
            }

            $location = trim( $response->header( 'Location' ) );
            $response->toPsrResponse()->getBody()->close();

            if( $location === '' || $redirects >= 2 ) {
                throw new InvalidException( sprintf( 'Too many or invalid redirects for "%s"', $url ) );
            }

            $url = (string) UriResolver::resolve( new Uri( $url ), new Uri( $location ) );
        }
    }


    /**
     * Resolves a hostname to an allowed IP address.
     *
     * Private and reserved ranges are accepted unless "cms.allow-internal" is
     * disabled. Literal IP hosts are validated directly without a DNS lookup.
     *
     * @param string $host The hostname or IP address to resolve
     * @return string|null The first allowed IP address, or null if none found
     */
    public static function resolve( string $host ) : ?string
    {
        $flags = config( 'cms.allow-internal', false )
            ? 0 : FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;

        // Literal IP host: validate directly, a DNS lookup would never resolve it
        if( filter_var( $host, FILTER_VALIDATE_IP ) ) {
            return filter_var( $host, FILTER_VALIDATE_IP, $flags ) ? $host : null;
        }

        foreach( @dns_get_record( $host, DNS_A + DNS_AAAA ) ?: [] as $r )
        {
            $ip = $r['ip'] ?? $r['ipv6'] ?? null;

            if( $ip && filter_var( $ip, FILTER_VALIDATE_IP, $flags ) ) {
                return $ip;
            }
        }

        // dns_get_record( DNS_A | DNS_AAAA ) misses CNAME-only hosts on some
        // resolvers; fall back to the system resolver to avoid rejecting them.
        foreach( @gethostbynamel( $host ) ?: [] as $ip )
        {
            if( filter_var( $ip, FILTER_VALIDATE_IP, $flags ) ) {
                return $ip;
            }
        }

        return null;
    }


    /**
     * Returns internal Guzzle HTTP options that mitigate SSRF for the given URL.
     *
     * Validates the URL syntactically, resolves the host once and pins the
     * connection to that IP (preventing DNS rebinding). Redirects are disabled
     * here and followed by request(), which repeats validation and pinning for
     * every target. Private/reserved targets are allowed unless
     * "cms.allow-internal" is disabled.
     *
     * @param string $url The http(s) URL that will be fetched
     * @return array<string, mixed> Options to pass to Http::withOptions()
     * @throws Exception If the URL is invalid or the host does not resolve
     */
    protected static function options( string $url ) : array
    {
        // Syntactic validation only; the host is resolved once below and the
        // result reused for both the allow-check and the connection pin.
        if( !Utils::isValidUrl( $url, strict: false ) ) {
            throw new InvalidException( sprintf( 'Invalid or unsafe URL "%s"', $url ) );
        }

        $parsed = (array) parse_url( $url );
        $host = (string) ( $parsed['host'] ?? '' );
        $port = $parsed['port'] ?? ( ( $parsed['scheme'] ?? '' ) === 'https' ? 443 : 80 );

        if( !( $ip = static::resolve( $host ) ) ) {
            throw new InvalidException( sprintf( 'Host "%s" does not resolve to an allowed address', $host ) );
        }

        return [
            'verify' => true,
            'connect_timeout' => 10,
            'allow_redirects' => false,
            'curl' => [
                // IPv6 addresses must be bracketed in CURLOPT_RESOLVE entries
                CURLOPT_RESOLVE => [$host . ':' . $port . ':' . ( str_contains( $ip, ':' ) ? '[' . $ip . ']' : $ip )],
                // Disable proxies from the environment, they would connect instead of the pinned IP
                CURLOPT_PROXY => '',
            ],
        ];
    }
}

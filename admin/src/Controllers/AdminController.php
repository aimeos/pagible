<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Controllers;

use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;


class AdminController extends Controller
{
    public function index(): Response
    {
        $media = config( 'cms.admin.csp.media-src' );
        $nonce = base64_encode( random_bytes( 16 ) );

        return response()
            ->view('cms::layouts.admin', ['nonce' => $nonce] )
            ->header('Content-Security-Policy',
                "base-uri 'self';" .
                "default-src 'self' data: blob:;" .
                "style-src 'self' 'unsafe-inline';" .
                "script-src 'self' 'nonce-{$nonce}' blob:;" .
                "media-src 'self' data: blob: http: https: " . $media . ";" .
                "img-src 'self' data: blob: http: https: " . $media . ";" .
                "connect-src 'self' data: blob: ws: wss: http: https: " . $media . ";" .
                "frame-src 'self' http: https:;" .
                "worker-src 'self' blob:;"
            );
    }


    /**
     * Proxy requests to external URLs with support for range requests.
     *
     * @param Request $request
     * @return SymfonyResponse
     */
    public function proxy( Request $request ): SymfonyResponse
    {
        $method = strtoupper( $request->method() );

        if( $method === 'OPTIONS' ) {
            return $this->optionsResponse();
        }

        if( !in_array( $method, ['GET', 'HEAD'] ) ) {
            abort( 405, "Unsupported HTTP method: $method" );
        }

        try
        {
            $parts = explode( '|', base64_decode( (string) $request->query( 'token', '' ) ) );
            $expires = $parts[0] ?? '';
            $uid = $parts[1] ?? '';
            $hmac = $parts[2] ?? '';

            // The token is bound to the authenticated user (see UserResolver::token), so a
            // leaked token cannot be replayed by or for a different account.
            if( (int) $expires < now()->timestamp
                || (string) $uid !== (string) $request->user()?->getAuthIdentifier()
                || !hash_equals( hash_hmac( 'sha256', $expires . '|' . $uid, config( 'app.key' ) ), (string) $hmac )
            ) {
                abort( 403, 'Unauthorized' );
            }
        }
        catch( \Exception $e )
        {
            abort( 403, 'Unauthorized' );
        }

        $url = (string) $request->query( 'url' );
        $range = $request->header( 'Range' ) ?: null;

        if( empty( $url ) || !\Aimeos\Cms\Utils::isValidUrl( $url ) ) {
            abort( 400, 'Invalid or missing URL' );
        }

        try
        {
            // fetch() resolves and pins the host to its public IP and rejects private targets.
            $response = $this->fetch( $url, $method, $range );
        }
        catch( \Exception $e )
        {
            Log::warning( 'Proxy fetch failed', ['url' => $url, 'error' => $e->getMessage()] );
            abort( 504, 'Upstream request timed out' );
        }

        $headers = $this->buildHeaders( $response, $range );

        $statusCode = isset( $headers['Content-Range'] ) ? 206 : $response->status();
        $maxBytes = (int) ( $headers['Content-Length'] ?? 0 ) ?: config( 'cms.admin.proxy.maxsize', 10 ) * 1024 * 1024;

        if( $method === 'HEAD' ) {
            return response( '', $statusCode, $headers );
        }

        return response()->stream( function() use ( $response, $maxBytes ) {
            $this->stream( $response->toPsrResponse()->getBody(), $maxBytes );
        }, $statusCode, $headers );
    }


    /**
     * Build headers for the response, including content length and range.
     *
     * @param ClientResponse $response
     * @param string|null $range
     * @return array<string, mixed>
     */
    protected function buildHeaders( ClientResponse $response, ?string $range ): array
    {
        $maxBytes = config('cms.admin.proxy.maxsize', 10) * 1024 * 1024;
        $rawLength = (int) ($response->header('Content-Length') ?: 0);
        $contentLength = min( $rawLength, $maxBytes );
        $contentRange = null;

        if( $rawLength > $maxBytes && !$range ) {
            $contentRange = "bytes 0-" . ($maxBytes - 1) . "/$rawLength";
        }
        elseif( $range && preg_match( '/bytes=(\d+)-(\d*)/', $range, $m ) )
        {
            $start = (int) $m[1];
            $end = $m[2] !== '' ? (int) $m[2] : ($start + $maxBytes - 1);
            $end = min($end, $start + $maxBytes - 1);
            $contentLength = $end - $start + 1;
            $contentRange = "bytes $start-$end/$rawLength";
        }

        $headers = [
            // Restrict to safe media types so attacker-controlled upstream content cannot be
            // served as executable HTML from the application origin; prevent MIME sniffing.
            'X-Content-Type-Options' => 'nosniff',
            'Content-Type' => $this->contentType( $response ),
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Content-Length, Content-Range, Accept-Encoding, Range',
            'Accept-Ranges' => 'bytes',
        ];

        if( $contentLength > 0 ) {
            $headers['Content-Length'] = $contentLength;
        }

        if( $contentRange ) {
            $headers['Content-Range'] = $contentRange;
        }

        return $headers;
    }


    /**
     * Returns a safe response content type for the proxied upstream content.
     *
     * Only media types are passed through; anything else (e.g. text/html) is downgraded to
     * application/octet-stream so it cannot execute as script on the application origin.
     *
     * @param ClientResponse $response
     * @return string
     */
    protected function contentType( ClientResponse $response ): string
    {
        $mime = strtolower( trim( explode( ';', (string) $response->header( 'Content-Type' ) )[0] ) );
        $safe = ['image/', 'audio/', 'video/', 'font/', 'application/pdf'];

        foreach( $safe as $prefix )
        {
            if( $mime !== '' && str_starts_with( $mime, $prefix ) ) {
                return $mime;
            }
        }

        return 'application/octet-stream';
    }


    /**
     * Fetch the content from the given URL using the specified method and range.
     *
     * Each request is pinned to the host's resolved public IP via CURLOPT_RESOLVE, and at most
     * one redirect is followed manually so the redirect target is re-validated and re-pinned
     * (preventing DNS-rebinding/redirect SSRF to private addresses).
     *
     * @param string $url
     * @param string $method
     * @param string|null $range
     * @param int $hops Remaining number of redirects that may be followed
     * @return ClientResponse
     */
    protected function fetch( string $url, string $method, ?string $range, int $hops = 1 ): ClientResponse
    {
        $parsed = parse_url( $url );
        $host = $parsed['host'] ?? '';
        $port = $parsed['port'] ?? ( ( $parsed['scheme'] ?? '' ) === 'https' ? 443 : 80 );

        if( !$host || !( $ip = \Aimeos\Cms\Utils::resolve( $host ) ) ) {
            throw new \Aimeos\Cms\Exception( "Host '$host' blocked" );
        }

        $headers = [
            'User-Agent' => 'Pagible-Proxy/1.0',
            'Accept-Encoding' => 'identity',
        ];

        if( $range ) {
            $headers['Range'] = $range;
        }

        $response = Http::withHeaders( $headers )
            ->timeout( 10 )
            ->withOptions( [
                'stream' => true,
                'verify' => true,
                'allow_redirects' => false,
                'curl' => [CURLOPT_RESOLVE => ["$host:$port:$ip"]],
            ] )
            ->send( $method, $url );

        if( $hops > 0 && $response->redirect() && ( $location = $response->header( 'Location' ) ) )
        {
            $target = $this->location( $url, $location );

            if( !\Aimeos\Cms\Utils::isValidUrl( $target ) ) {
                throw new \Aimeos\Cms\Exception( "Redirect to '$location' blocked" );
            }

            return $this->fetch( $target, $method, $range, $hops - 1 );
        }

        return $response;
    }


    /**
     * Resolves a redirect Location (absolute or root-relative) against the requested URL.
     *
     * @param string $base Originally requested URL
     * @param string $location Location header value from the redirect response
     * @return string Absolute target URL
     */
    protected function location( string $base, string $location ): string
    {
        if( preg_match( '#^https?://#i', $location ) ) {
            return $location;
        }

        $parts = parse_url( $base );
        $origin = ( $parts['scheme'] ?? 'https' ) . '://' . ( $parts['host'] ?? '' )
            . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );

        return $origin . '/' . ltrim( $location, '/' );
    }


    /**
     * Handle OPTIONS requests for CORS preflight checks.
     *
     * @return Response
     */
    protected function optionsResponse(): Response
    {
        return response('', 204, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Content-Length, Content-Range, Accept-Encoding, Range',
        ]);
    }


    /**
     * Stream the response body, respecting the maximum byte limit.
     *
     * @param \Psr\Http\Message\StreamInterface $body
     * @param int $maxBytes
     */
    protected function stream( \Psr\Http\Message\StreamInterface $body, int $maxBytes ): void
    {
        $sent = 0;
        $chunkSize = 1048576; // 1MB
        $timeout = config( 'cms.admin.proxy.timeout', 30 ); // default: 30 seconds
        $start = time();

        while( ob_get_level() > 0 ) {
            ob_end_flush();
        }

        while( !$body->eof() && $sent < $maxBytes )
        {
            if( ( time() - $start ) > $timeout ) {
                Log::warning( 'Stream timed out', ['sent' => $sent, 'maxBytes' => $maxBytes, 'timeout' => $timeout] );
                break;
            }

            $chunk = $body->read( $chunkSize );
            $sent += strlen( $chunk );

            echo $chunk;
            flush();
        }
    }
}

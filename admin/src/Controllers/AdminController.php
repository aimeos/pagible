<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Controllers;

use Aimeos\Cms\FileResponse;
use Aimeos\Cms\Permission;
use Aimeos\Cms\ProxyToken;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;


class AdminController extends Controller
{
    private const CORS = [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Content-Length, Content-Range, Accept-Encoding, Range',
    ];


    /**
     * Delivers a private File to an authenticated CMS editor.
     */
    public function asset( Request $request, string $file,
        int|string|null $variant = null ) : SymfonyResponse
    {
        if( !Permission::can( 'file:view', $request->user() ) ) {
            abort( 403 );
        }

        return FileResponse::make( $file, $variant, true );
    }


    public function index(): Response
    {
        $nonce = base64_encode( random_bytes( 16 ) );

        return response()
            ->view('cms::layouts.admin', ['nonce' => $nonce] )
            ->header('Content-Security-Policy',
                "base-uri 'self';" .
                "default-src 'self' data: blob:;" .
                "style-src 'self' 'unsafe-inline';" .
                "script-src 'self' 'nonce-{$nonce}' blob:;" .
                "media-src 'self' data: blob: http: https:;" .
                "img-src 'self' data: blob: http: https:;" .
                "connect-src 'self' data: blob: ws: wss: http: https:;" .
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
    public function proxy( Request $request, ProxyToken $token ): SymfonyResponse
    {
        $method = strtoupper( $request->method() );

        if( $method === 'OPTIONS' ) {
            return response( '', 204, self::CORS );
        }

        $user = $request->user();

        if( !$user instanceof Authenticatable || !$token->valid( (string) $request->query( 'token', '' ), $user ) ) {
            abort( 403, 'Unauthorized' );
        }

        $url = (string) $request->query( 'url' );
        $range = $request->header( 'Range' ) ?: null;

        if( empty( $url ) || !\Aimeos\Cms\Utils::isValidUrl( $url ) ) {
            abort( 400, 'Invalid or missing URL' );
        }

        try
        {
            // Resolves and pins every host to its public IP and rejects private targets
            $response = \Aimeos\Cms\Utils::http( $url, ['stream' => true, 'timeout' => 10], array_filter( [
                'User-Agent' => 'Pagible-Proxy/1.0',
                'Accept-Encoding' => 'identity',
                'Range' => $range,
            ] ), $method );
        }
        catch( \Exception $e )
        {
            Log::warning( 'Proxy fetch failed', ['url' => $url, 'error' => $e->getMessage()] );
            abort( 504, 'Upstream request timed out' );
        }

        $maxsize = (int) config( 'cms.admin.proxy.maxsize', 10 ) * 1024 * 1024;
        $headers = $this->buildHeaders( $response, $maxsize );

        $statusCode = isset( $headers['Content-Range'] ) ? 206 : $response->status();
        $maxBytes = $headers['Content-Length'] ?? $maxsize;

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
     * Partial upstream responses keep their range. Complete upstream responses larger than
     * the maximum size are truncated and returned as the first range of the content.
     *
     * @param ClientResponse $response Upstream response
     * @param int $maxsize Maximum number of bytes to send
     * @return array<string, mixed>
     */
    protected function buildHeaders( ClientResponse $response, int $maxsize ): array
    {
        $length = (int) $response->header( 'Content-Length' );
        $partial = $response->status() === 206;

        $headers = [
            // Restrict to safe media types so attacker-controlled upstream content cannot be
            // served as executable HTML from the application origin; prevent MIME sniffing.
            'X-Content-Type-Options' => 'nosniff',
            'Content-Type' => $this->contentType( $response ),
            'Accept-Ranges' => 'bytes',
        ] + self::CORS;

        if( $partial && preg_match( '#^bytes (\d+)-(\d+)/(\d+|\*)$#', $response->header( 'Content-Range' ), $m ) )
        {
            $start = (int) $m[1];
            $end = min( (int) $m[2], $start + $maxsize - 1 );
            $headers['Content-Length'] = $end - $start + 1;
            $headers['Content-Range'] = "bytes $start-$end/{$m[3]}";
        }
        elseif( !$partial && $length > $maxsize )
        {
            $headers['Content-Length'] = $maxsize;
            $headers['Content-Range'] = 'bytes 0-' . ( $maxsize - 1 ) . "/$length";
        }
        elseif( $length > 0 )
        {
            $headers['Content-Length'] = min( $length, $maxsize );
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

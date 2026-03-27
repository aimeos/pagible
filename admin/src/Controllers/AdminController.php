<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
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
        $nonce = base64_encode(random_bytes(16));

        return response()
            ->view('cms::layouts.admin', compact('nonce'))
            ->header('Content-Security-Policy',
                "base-uri 'self';" .
                "default-src 'self' data: blob:;" .
                "style-src 'self' 'unsafe-inline';" .
                "script-src 'self' 'nonce-{$nonce}' blob:;" .
                "media-src 'self' data: blob: http: https: " . $media . ";" .
                "img-src 'self' data: blob: http: https: " . $media . ";" .
                "connect-src 'self' http: https: " . $media . ";" .
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
    public function proxy(Request $request): SymfonyResponse
    {
        $method = strtoupper($request->method());

        if ($method === 'OPTIONS') {
            return $this->optionsResponse();
        }

        if (!in_array($method, ['GET', 'HEAD'])) {
            abort(405, "Unsupported HTTP method: $method");
        }

        $url = (string) $request->query('url');
        $range = $request->header('Range') ?: null;

        if (!\Aimeos\Cms\Utils::isValidUrl($url)) {
            abort(400, 'Invalid or missing URL');
        }

        try {
            $response = $this->fetch($url, $method, $range);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('Proxy fetch failed', ['url' => $url, 'error' => $e->getMessage()]);
            abort(504, 'Upstream request timed out');
        }

        $headers = $this->buildHeaders($response, $range);

        $statusCode = isset( $headers['Content-Range'] ) ? 206 : $response->status();
        $maxBytes = (int) $headers['Content-Length'];

        return response()->stream(function () use ($response, $maxBytes) {
            $this->stream($response->toPsrResponse()->getBody(), $maxBytes);
        }, $statusCode, $headers);
    }


    /**
     * Build headers for the response, including content length and range.
     *
     * @param ClientResponse $response
     * @param string|null $range
     * @return array<string, mixed>
     */
    protected function buildHeaders(ClientResponse $response, ?string $range): array
    {
        $maxBytes = config('cms.admin.proxy.max-length', 10) * 1024 * 1024;
        $rawLength = (int) ($response->header('Content-Length') ?: 0);
        $contentLength = min($rawLength, $maxBytes);
        $contentRange = null;

        if ($rawLength > $maxBytes && !$range) {
            $contentRange = "bytes 0-" . ($maxBytes - 1) . "/$rawLength";
        } elseif ($range && preg_match('/bytes=(\d+)-(\d*)/', $range, $m)) {
            $start = (int) $m[1];
            $end = $m[2] !== '' ? (int) $m[2] : ($start + $maxBytes - 1);
            $end = min($end, $start + $maxBytes - 1);
            $contentLength = $end - $start + 1;
            $contentRange = "bytes $start-$end/$rawLength";
        }

        $headers = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Content-Length, Content-Range, Accept-Encoding, Range',
            'Accept-Ranges' => 'bytes',
            'Content-Length' => $contentLength,
            'Content-Type' => $response->header('Content-Type') ?: 'application/octet-stream',
        ];

        if ($contentRange) {
            $headers['Content-Range'] = $contentRange;
        }

        return $headers;
    }


    /**
     * Fetch the content from the given URL using the specified method and range.
     *
     * @param string $url
     * @param string $method
     * @param string|null $range
     * @return ClientResponse
     */
    protected function fetch(string $url, string $method, ?string $range): ClientResponse
    {
        $headers = [
            'User-Agent' => 'Pagible-Proxy/1.0',
            'Accept-Encoding' => 'identity',
        ];

        if( $range ) {
            $headers['Range'] = $range;
        }

        return Http::withHeaders($headers)
            ->timeout(10)
            ->withOptions([
                'stream' => true,
                'verify' => true
            ])
            ->send($method, $url);
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
    protected function stream(\Psr\Http\Message\StreamInterface $body, int $maxBytes): void
    {
        $sent = 0;
        $chunkSize = 1048576; // 1MB
        $timeout = config('cms.admin.proxy.stream_timeout', 30); // default: 30 seconds
        $start = time();

        while (ob_get_level() > 0) ob_end_flush();

        while (!$body->eof() && $sent < $maxBytes) {
            if ((time() - $start) > $timeout) {
                Log::warning('Stream timed out', ['sent' => $sent, 'maxBytes' => $maxBytes, 'timeout' => $timeout]);
                break;
            }

            $chunk = $body->read($chunkSize);
            $sent += strlen($chunk);

            echo $chunk;
            flush();
        }
    }
}

<?php

namespace Aimeos\Cms\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class AdminController extends Controller
{
    public function index()
    {
        $nonce = base64_encode(random_bytes(16));
        return response()
            ->view('cms::layouts.admin', compact('nonce'))
            ->header('Content-Security-Policy',
                "base-uri 'self';" .
                "default-src 'self' data: blob:;" .
                "style-src 'self' 'unsafe-inline';" .
                "script-src 'self' 'nonce-{$nonce}';" .
                "img-src 'self' data: blob: http: https:;" .
                "media-src 'self' data: blob: http: https:;"
            );
    }


    /**
     * Proxy requests to external URLs with support for range requests.
     *
     * @param Request $request
     * @return Response
     */
    public function proxy(Request $request)
    {
        $url = $request->query('url');

        if (!$this->isValidUrl($url)) {
            abort(400, 'Invalid or missing URL');
        }

        $method = strtoupper($request->method());

        if ($method === 'OPTIONS') {
            return $this->optionsResponse();
        }

        if (!in_array($method, ['GET', 'HEAD'])) {
            abort(405, "Unsupported HTTP method: $method");
        }

        $range = $request->header('Range');
        $response = $this->fetch($url, $method, $range);
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
     * @param Response $response
     * @param string|null $range
     * @return array
     */
    protected function buildHeaders(Response $response, ?string $range): array
    {
        $maxBytes = config('cms.proxy.max-length', 10) * 1024 * 1024;
        $rawLength = (int) $response->header('Content-Length', 0);
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
            'Content-Type' => $response->header('Content-Type', 'application/octet-stream'),
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
     * @return Response
     */
    protected function fetch(string $url, string $method, ?string $range): Response
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (!$host || !($ip = $this->ip($host))) {
            abort(400, 'Invalid or inaccessible host');
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        $path = parse_url($url, PHP_URL_PATH) ?? '/';
        $query = parse_url($url, PHP_URL_QUERY);
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
     * Resolve the IP address of the given host, caching the result.
     *
     * @param string $host
     * @return string|null
     */
    protected function ip(string $host): ?string
    {
        return Cache::remember("cmsproxy:$host", 60, function () use ($host) {
            return collect(dns_get_record($host, DNS_A + DNS_AAAA))
                ->map(fn($r) => $r['ip'] ?? $r['ipv6'] ?? null)
                ->filter(fn($ip) =>
                    $ip && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
                )->first();
        });
    }


    /**
     * Validate the given URL.
     *
     * @param string|null $url
     * @return bool
     */
    protected function isValidUrl(?string $url): bool
    {
        if (!$url || strlen($url) > 2048) {
            return false;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parsed = parse_url($url);

        if (!in_array($parsed['scheme'] ?? '', ['http', 'https'])) {
            return false;
        }

        if (empty($parsed['host']) || !filter_var($parsed['host'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return false;
        }

        if (!empty($parsed['path']) && preg_match('/(\.\.|[\x00-\x1F\x7F])/', $parsed['path'])) {
            return false;
        }

        return true;
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
        $timeout = config('cms.proxy.stream_timeout', 30); // default: 30 seconds
        $start = time();

        while (ob_get_level() > 0) ob_end_flush();

        while (!$body->eof() && $sent < $maxBytes) {
            if ((time() - $start) > $timeout) {
                Log::warning('Stream timed out');
                break;
            }

            $chunk = $body->read($chunkSize);
            $sent += strlen($chunk);

            echo $chunk;
            flush();
        }
    }
}

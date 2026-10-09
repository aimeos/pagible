<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\PumpStream;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;


/**
 * Guzzle handler for streamed responses using cURL.
 *
 * Guzzle's stream handler uses PHP's HTTP wrapper, which ignores "curl" options like the
 * CURLOPT_RESOLVE pin added by Utils::safeHttp() and resolves the host again, while its cURL
 * handlers download the complete body first. This handler applies all "curl" options, returns
 * once the headers are received and transfers the body while it's read.
 *
 * Supported request options: "connect_timeout", "curl", "timeout" (seconds without receiving
 * data, like the stream handler) and "verify". Redirects are never followed.
 */
final class HttpStream
{
    /**
     * Sends the request and returns the response with a streamed body.
     *
     * @param RequestInterface $request Request to send
     * @param array<string, mixed> $options Guzzle request options
     * @return PromiseInterface Fulfilled with the response or rejected with the transfer error
     */
    public function __invoke( RequestInterface $request, array $options ) : PromiseInterface
    {
        try {
            return Create::promiseFor( $this->send( $request, $options ) );
        } catch( \Throwable $e ) {
            return Create::rejectionFor( $e );
        }
    }


    /**
     * Returns the cURL options for the request.
     *
     * @param RequestInterface $request Request to send
     * @param array<string, mixed> $options Guzzle request options
     * @return array<int, mixed> cURL options
     */
    private function conf( RequestInterface $request, array $options ) : array
    {
        $verify = $options['verify'] ?? true;
        $timeout = (float) ( $options['timeout'] ?? 0 );
        $headers = [];

        foreach( $request->getHeaders() as $name => $values )
        {
            foreach( $values as $value ) {
                $headers[] = $name . ': ' . $value;
            }
        }

        $conf = [
            CURLOPT_URL => (string) $request->getUri()->withFragment( '' ),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT_MS => (int) ( (float) ( $options['connect_timeout'] ?? 0 ) * 1000 ),
            CURLOPT_SSL_VERIFYPEER => $verify !== false,
            CURLOPT_SSL_VERIFYHOST => $verify !== false ? 2 : 0,
        ];

        if( is_string( $verify ) ) {
            $conf[is_dir( $verify ) ? CURLOPT_CAPATH : CURLOPT_CAINFO] = $verify;
        }

        if( $timeout > 0 )
        {
            $conf[CURLOPT_LOW_SPEED_LIMIT] = 1;
            $conf[CURLOPT_LOW_SPEED_TIME] = (int) ceil( $timeout );
        }

        if( $request->getMethod() === 'HEAD' ) {
            $conf[CURLOPT_NOBODY] = true;
        } elseif( $request->getMethod() !== 'GET' ) {
            $conf[CURLOPT_CUSTOMREQUEST] = $request->getMethod();
        }

        if( ( $body = (string) $request->getBody() ) !== '' ) {
            $conf[CURLOPT_POSTFIELDS] = $body;
        }

        return (array) ( $options['curl'] ?? [] ) + $conf;
    }


    /**
     * Starts the transfer and waits until the response headers are received.
     *
     * @param RequestInterface $request Request to send
     * @param array<string, mixed> $options Guzzle request options
     * @return Response Response whose body continues the transfer while it's read
     * @throws ConnectException If no complete response header is received
     */
    private function send( RequestInterface $request, array $options ) : Response
    {
        $buffer = $reason = '';
        $version = '1.1';
        $headers = [];
        $status = 0;
        $done = false;
        $running = 1;

        $ch = curl_init();
        $mh = curl_multi_init();

        curl_setopt_array( $ch, $this->conf( $request, $options ) + [
            CURLOPT_HEADERFUNCTION => function( $ch, string $line ) use ( &$status, &$reason, &$version, &$headers, &$done ) : int {
                $value = trim( $line );

                if( preg_match( '#^HTTP/(\S+)\s+(\d{3})\s*(.*)$#', $value, $m ) ) {
                    [$version, $status, $reason, $headers] = [$m[1], (int) $m[2], $m[3], []];
                } elseif( $value === '' ) {
                    $done = $status >= 200;
                } elseif( str_contains( $value, ':' ) ) {
                    [$name, $val] = explode( ':', $value, 2 );
                    $headers[trim( $name )][] = trim( $val );
                }

                return strlen( $line );
            },
            CURLOPT_WRITEFUNCTION => function( $ch, string $data ) use ( &$buffer ) : int {
                $buffer .= $data;
                return strlen( $data );
            },
        ] );
        curl_multi_add_handle( $mh, $ch );

        $step = function() use ( $mh, &$running ) : void {
            if( curl_multi_select( $mh, 1.0 ) === -1 ) {
                usleep( 1000 );
            }

            do {
                $code = curl_multi_exec( $mh, $running );
            } while( $code === CURLM_CALL_MULTI_PERFORM );
        };
        $error = function() use ( $mh, $ch ) : ?string {
            $info = curl_multi_info_read( $mh );
            $code = is_array( $info ) ? $info['result'] : CURLE_OK;

            return $code !== CURLE_OK ? ( curl_error( $ch ) ?: curl_strerror( $code ) ) : null;
        };

        curl_multi_exec( $mh, $running );

        while( !$done && $running ) {
            $step();
        }

        if( !$done ) {
            throw new ConnectException( sprintf( 'Request to "%s" failed: %s', $request->getUri(), $error() ?? 'Incomplete response' ), $request );
        }

        $body = new PumpStream( function( int $length ) use ( &$buffer, &$running, $step, $error ) {
            while( $buffer === '' && $running ) {
                $step();
            }

            if( $buffer === '' )
            {
                if( $msg = $error() ) {
                    throw new \RuntimeException( sprintf( 'Response transfer failed: %s', $msg ) );
                }

                return false;
            }

            $data = substr( $buffer, 0, $length );
            $buffer = (string) substr( $buffer, $length );

            return $data;
        } );

        return new Response( $status, $headers, $body, $version, $reason );
    }
}

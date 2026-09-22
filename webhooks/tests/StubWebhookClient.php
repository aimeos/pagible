<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\WebhookClient;
use Illuminate\Support\Carbon;


final class StubWebhookClient extends WebhookClient
{
    /** @var list<string> */
    public array $addresses = ['93.184.216.34'];
    /** @var list<string> Response header lines passed to the header callback */
    public array $headers = [];
    /** @var list<array{string, bool}> Host names and if the system resolver may be asked */
    public array $lookups = [];
    /** @var array<int, mixed> */
    public array $options = [];
    public ?string $overflow = null;
    public int $errno = 0;
    /** Milliseconds the host name resolution takes */
    public int $resolveTime = 0;
    public int $status = 204;


    /**
     * @param array<int, mixed> $options
     * @return array{bool, int, int}
     */
    protected function execute( array $options ) : array
    {
        $this->options = $options;

        // The status is known when the body arrives, WebhookClient stops the transfer then
        if( $this->overflow === 'body' ) {
            call_user_func( $options[CURLOPT_WRITEFUNCTION], null, '{"ok":true}' );
            return [false, $this->status, 23];
        }

        if( $this->overflow === 'headers' ) {
            // The header lines exceed the limit of WebhookClient
            call_user_func( $options[CURLOPT_HEADERFUNCTION], null, str_repeat( 'x', 32769 ) );
            return [false, 0, 23];
        }

        if( $this->errno ) {
            return [false, 0, $this->errno];
        }

        foreach( $this->headers as $line ) {
            call_user_func( $options[CURLOPT_HEADERFUNCTION], null, $line . "\r\n" );
        }

        return [true, $this->status, 0];
    }


    /**
     * @return list<string>
     */
    protected function lookup( string $host, bool $system = false ) : array
    {
        $this->lookups[] = [$host, $system];

        if( $this->resolveTime ) {
            Carbon::setTestNow( now()->addMilliseconds( $this->resolveTime ) );
        }

        return $this->addresses;
    }
}

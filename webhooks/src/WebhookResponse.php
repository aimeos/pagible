<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;


/**
 * Result of one webhook request.
 */
final class WebhookResponse
{
    /**
     * @param int $status HTTP response status
     * @param int $retryAfter Seconds the receiver asked to wait before the next request, 0 if none
     */
    public function __construct(
        public readonly int $status,
        public readonly int $retryAfter = 0,
    ) {
    }


    /**
     * Tests if the receiver is temporarily unable to accept the delivery.
     */
    public function retryable() : bool
    {
        return in_array( $this->status, [408, 425, 429], true ) || $this->status >= 500;
    }


    public function successful() : bool
    {
        return $this->status >= 200 && $this->status < 300;
    }
}

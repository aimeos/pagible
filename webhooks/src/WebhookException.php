<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;


final class WebhookException extends \RuntimeException
{
    public function __construct( public readonly string $reason )
    {
        parent::__construct( $reason );
    }
}

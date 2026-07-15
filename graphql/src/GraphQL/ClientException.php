<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL;

use GraphQL\Error\ClientAware;


final class ClientException extends \Exception implements ClientAware
{
    public function __construct( \Aimeos\Cms\Exception $exception )
    {
        parent::__construct( $exception->getMessage(), $exception->getCode(), $exception );
    }


    public function isClientSafe(): bool
    {
        return true;
    }
}

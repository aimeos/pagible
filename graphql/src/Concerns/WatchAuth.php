<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Aimeos\Cms\Events\Authed;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Watch;


trait WatchAuth
{
    /**
     * Dispatches an authentication audit event when watch logging is enabled.
     */
    protected function authWatch( string $action, string $email ) : void
    {
        Watch::dispatchWhen( null, fn() => new Authed(
            $action,
            $email,
            (string) request()->ip(),
            (string) request()->userAgent(),
            Tenancy::value()
        ) );
    }
}

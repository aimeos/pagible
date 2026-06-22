<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;


/**
 * Builds the websocket channel names shared by the broadcast events and their channel
 * authorization, so the per-type naming convention lives in a single place.
 */
class Channel
{
    /**
     * Per-type channel for the list/tree views, e.g. "cms.acme.page" - or "cms.page" when no
     * tenant is active (single-tenant setup), since an empty segment would break wildcard auth.
     */
    public static function type( string $tenant, string $type ) : string
    {
        return $tenant === '' ? 'cms.' . $type : 'cms.' . $tenant . '.' . $type;
    }
}

<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Prism\Prism\Tool;
use Aimeos\Cms\Models\Page;


class Locales extends Tool
{
    public function __construct()
    {
        $this->as( 'get-locales' )
            ->for( 'Returns the list of available ISO language codes for the page and its content as JSON array.' )
            ->using( $this );
    }


    public function __invoke(): string
    {
        return response()->json( config( 'cms.config.locales', [] ) );
    }
}

<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Pulse;


class CmsJsonapiCard extends CmsCard
{
    public function render()
    {
        return view( 'cms-pulse::cms-jsonapi-card', [
            'title' => 'JSON:API',
            'entries' => $this->summary( 'cms_jsonapi', ['count', 'avg', 'max'], 'action', fn( $rows ) =>
                $this->detail( $rows, 'domain', 'includes' )
            ),
        ] );
    }
}

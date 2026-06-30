<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Pulse;


class CmsAuthCard extends CmsCard
{
    public function render()
    {
        return view( 'cms-pulse::cms-auth-card', [
            'title' => 'Authentication',
            'entries' => $this->summary( 'cms_auth', 'count', 'action', fn( $rows ) =>
                $this->detail( $rows, 'email', 'ip' )
            ),
        ] );
    }
}

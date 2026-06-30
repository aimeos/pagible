<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Pulse;


class CmsElementCard extends CmsCard
{
    public function render()
    {
        return view( 'cms-pulse::cms-element-card', [
            'title' => 'Elements',
            'entries' => $this->summary( 'cms_element', 'count', 'action', fn( $rows ) =>
                $this->detail( $rows, 'editor', 'source' )
            ),
        ] );
    }
}

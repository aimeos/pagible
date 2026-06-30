<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Pulse;


class CmsPageCard extends CmsCard
{
    public function render()
    {
        return view( 'cms-pulse::cms-page-card', [
            'title' => 'Pages',
            'entries' => $this->summary( 'cms_page', 'count', 'action', fn( $rows ) =>
                $this->detail( $rows, 'editor', 'path', 'domain' )
            ),
        ] );
    }
}

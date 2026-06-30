<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Pulse;


class CmsFileCard extends CmsCard
{
    public function render()
    {
        return view( 'cms-pulse::cms-file-card', [
            'title' => 'Files',
            'entries' => $this->summary( 'cms_file', 'count', 'action', fn( $rows ) =>
                $this->detail( $rows, 'editor', 'mime', 'source' )
            ),
        ] );
    }
}

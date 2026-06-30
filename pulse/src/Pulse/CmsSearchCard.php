<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Pulse;


class CmsSearchCard extends CmsCard
{
    public function render()
    {
        return view( 'cms-pulse::cms-search-card', [
            'title' => 'Search',
            'entries' => $this->summary( 'cms_search', ['count', 'avg', 'max'], 'query', fn( $rows ) =>
                $this->detail( $rows, 'domain', 'lang' )
            ),
        ] );
    }
}

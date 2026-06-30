<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Pulse;


class CmsContactCard extends CmsCard
{
    public function render()
    {
        return view( 'cms-pulse::cms-contact-card', [
            'title' => 'Contact',
            'entries' => $this->summary( 'cms_contact', ['count', 'avg', 'max'], 'ip', fn( $rows ) =>
                $this->detail( $rows, 'email' )
            ),
        ] );
    }
}

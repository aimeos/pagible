<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Pulse;


class CmsAiCard extends CmsCard
{
    public function render()
    {
        return view( 'cms-pulse::cms-ai-card', [
            'title' => 'AI',
            'entries' => $this->summary( 'cms_ai', ['count', 'avg', 'max'], 'mutation', fn( $rows ) =>
                trim( implode( ' | ', array_filter( [
                    $this->detail( $rows, 'provider', 'model' ),
                    $this->successRate( $rows ),
                ] ) ) )
            ),
        ] );
    }
}

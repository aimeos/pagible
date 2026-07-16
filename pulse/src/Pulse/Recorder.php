<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Pulse;

use Aimeos\Cms\Events\Observed;
use Aimeos\Cms\Watch;
use Laravel\Pulse\Facades\Pulse;


class Recorder
{
    /**
     * @var list<class-string>
     */
    public array $listen = [Observed::class];


    public function record( Observed $event ) : void
    {
        if( $event->sample && !Watch::sampled() ) {
            return;
        }

        Pulse::record(
            Metric::type( 'cms_' . $event->source, $event->tenant ),
            $this->key( [...$event->dimensions, 'action' => $event->action] ),
            max( 0, (int) round( $event->durationMs ) ),
        )->count()->avg()->max();
    }


    /**
     * @param array<string, mixed> $fields
     */
    private function key( array $fields ) : string
    {
        $fields = array_filter( $fields, fn( $value ) => $value !== null && $value !== '' );

        try {
            return json_encode( $fields, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES );
        } catch( \JsonException ) {
            return '{}';
        }
    }
}

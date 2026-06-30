<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Recorders;

use Aimeos\Cms\Events\Generated;


class CmsAiPulseRecorder extends Recorder
{
    public string $listen = Generated::class;


    public function record( mixed $event ) : void
    {
        if( !$event instanceof Generated ) {
            return;
        }

        $key = [
            'mutation' => $this->prefixed( 'ai', $event->mutation ),
            'provider' => $event->provider,
            'model' => $event->model,
            'editor' => $event->editor,
            'tenant' => $event->tenant,
            'success' => $event->success,
        ];

        $this->entry( 'cms_ai', $key, $this->ms( $event->durationMs ), ['count', 'avg', 'max'] );

        if( $event->inputTokens !== null ) {
            $this->entry( 'cms_ai_input_tokens', $key, $event->inputTokens, ['sum'] );
        }

        if( $event->outputTokens !== null ) {
            $this->entry( 'cms_ai_output_tokens', $key, $event->outputTokens, ['sum'] );
        }
    }
}

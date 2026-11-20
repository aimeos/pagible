<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Ai;
use Aimeos\Cms\Media;
use Aimeos\Prisma\Files\Audio;


final class Transcribe
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array<int, mixed>
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        return Ai::transcribe( Media::uploaded( $args['file'], Audio::class ), $args['lang'] ?? null );
    }
}

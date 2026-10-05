<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Ai;
use Aimeos\Prisma\Files\Audio;


final class Transcribe
{
    use ValidatesInputs;


    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array<int, mixed>
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        $audio = $this->upload( $args['file'], Audio::class );

        return $this->ai( fn() => Ai::transcribe( $audio ) );
    }
}

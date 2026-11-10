<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Ai;
use Aimeos\Cms\Media;
use Aimeos\Prisma\Files\File as PrismaFile;
use Illuminate\Support\Facades\Auth;


final class Write
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ) : string
    {
        return Ai::write( $args['prompt'], Media::load( $args['files'] ?? [], Auth::user(), PrismaFile::class ), $args['context'] ?? null );
    }
}

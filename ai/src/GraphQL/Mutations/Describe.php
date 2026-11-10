<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Ai;
use Aimeos\Cms\Media;
use Aimeos\Prisma\Files\File as PrismaFile;
use Illuminate\Support\Facades\Auth;


final class Describe
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ) : string
    {
        return Ai::describe( Media::file( $args['file'], Auth::user(), PrismaFile::class ), $args['lang'] ?? null );
    }
}

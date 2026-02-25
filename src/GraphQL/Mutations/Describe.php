<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\File;
use Aimeos\Cms\Permission;
use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Exceptions\PrismaException;
use Illuminate\Support\Facades\Auth;
use GraphQL\Error\Error;


final class Describe
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ): string
    {
        if( !Permission::can( 'file:describe', Auth::user() ) ) {
            throw new Error( 'Insufficient permissions' );
        }

        if( empty( $id = $args['file'] ) ) {
            throw new Error( 'File ID is required' );
        }

        $provider = config( 'cms.ai.describe.provider' );
        $config = config( 'cms.ai.describe', [] );
        $model = config( 'cms.ai.describe.model' );

        try
        {
            /** @var File $file */
            $file = File::findOrFail( $id );
            $lang = $args['lang'] ?? null;
            $type = current( explode( '/', $file->mime ) );
            $class = '\\Aimeos\\Prisma\\Files\\' . ucfirst( $type );

            if( !class_exists( $class ) ) {
                throw new Error( 'Unsupported file type' );
            }

            if( !str_starts_with( (string) $file->path, 'http' ) ) {
                $doc = $class::fromStoragePath( $file->path, config( 'cms.disk', 'public' ), $file->mime );
            } else {
                $doc = $class::fromUrl( $file->path, $file->mime );
            }

            return Prisma::type( $type )
                ->using( $provider, $config )
                ->model( $model )
                ->ensure( 'describe' )
                ->describe( $doc, $lang, $config ) // @phpstan-ignore-line method.notFound
                ->text();
        }
        catch( PrismaException $e )
        {
            throw new Error( $e->getMessage(), null, null, null, null, $e );
        }
    }
}

<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\File;
use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Exceptions\PrismaException;
use Illuminate\Support\Facades\Log;
use GraphQL\Error\Error;


final class Describe
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ): string
    {
        if( empty( $id = $args['file'] ) ) {
            throw new Error( 'File ID is required' );
        }

        $provider = config( 'cms.ai.describe.provider' );
        $config = config( 'cms.ai.describe', [] );
        $model = config( 'cms.ai.describe.model' );

        try
        {
            /** @var File $file */
            $file = File::select( 'id', 'path', 'mime' )->findOrFail( $id );
            $lang = $args['lang'] ?? null;
            $type = explode( '/', $file->mime, 2 )[0];
            $class = '\\Aimeos\\Prisma\\Files\\' . ucfirst( $type );

            if( !class_exists( $class ) ) {
                $msg = 'Unsupported file type "%s"';
                throw new Error( sprintf( $msg, $file->mime ) );
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
            Log::error( 'AI service error', ['mutation' => 'Describe', 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()] );
            throw new Error( config( 'app.debug' ) ? $e->getMessage() : 'AI service error', null, null, null, null, $e );
        }
    }
}

<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\File;
use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Files\Image;
use Aimeos\Prisma\Exceptions\PrismaException;
use Illuminate\Support\Facades\Log;
use GraphQL\Error\Error;


final class Imagine
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ) : string
    {
        if( empty( $args['prompt'] ) ) {
            throw new Error( 'Prompt must not be empty' );
        }

        $provider = config( 'cms.ai.imagine.provider' );
        $config = config( 'cms.ai.imagine', [] );
        $model = config( 'cms.ai.imagine.model' );
        $options = ['size' => ['1536x1024', '1792x1024', '1024x1024']];

        try
        {
            return Prisma::image()
                ->using( $provider, $config )
                ->model( $model )
                ->ensure( 'imagine' )
                ->imagine( $args['prompt'], $this->files( $args['files'] ?? [] ), $options ) // @phpstan-ignore-line method.notFound
                ->base64();
        }
        catch( PrismaException $e )
        {
            Log::error( 'AI service error', ['mutation' => 'Imagine', 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()] );
            throw new Error( config( 'app.debug' ) ? $e->getMessage() : 'AI service error', null, null, null, null, $e );
        }
    }


    /**
     * @param array<mixed> $ids
     * @return array<mixed>
     */
    protected function files( array $ids ) : array
    {
        if( empty( $ids ) ) {
            return [];
        }

        $disk = config( 'cms.disk', 'public' );

        return File::whereIn( 'id', $ids )->select( 'id', 'path', 'mime' )->get()->map( function( $file ) use ( $disk ) {

            if( !str_starts_with( $file->mime, 'image/' ) ) {
                return null;
            }

            if( str_starts_with( (string) $file->path, 'http' ) ) {
                return Image::fromUrl( (string) $file->path, $file->mime );
            }

            return Image::fromStoragePath( (string) $file->path, $disk );

        } )->filter()->values()->toArray();
    }
}

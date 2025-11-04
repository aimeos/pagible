<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Files\Image;
use Aimeos\Prisma\Exceptions\PrismaException;
use Aimeos\Cms\Models\File;
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

        $files = collect();
        $sysPrompt = join( "\n\n", array_filter( [
            view( 'cms::prompts.imagine' )->render(),
            $args['context'] ?? ''
        ] ) );

        $provider = config( 'cms.ai.image' ) ?: 'gemini';
        $model = config( 'cms.ai.image-model' ) ?: 'gemini-2.5-flash-image';

        try
        {
            $prisma = Prisma::image()->using( $provider, config( 'prism.providers.' . $provider, [] ) )
                ->withSystemPrompt( $sysPrompt )
                ->withClientOptions( [
                    'connect_timeout' => 10,
                    'timeout' => 60,
                ] );

            if( !empty( $ids = $args['files'] ?? null ) )
            {
                $disk = config( 'cms.disk', 'public' );
                $files = File::where( 'id', $ids )->get()->map( function( $file ) use ( $disk ) {

                    if( !str_starts_with( $file->mime, 'image/' ) ) {
                        return null;
                    }

                    if( str_starts_with( $file->path, 'http' ) ) {
                        return Image::fromUrl( $file->path, $file->mime );
                    }

                    return Image::fromStoragePath( $file->path, $disk );

                } )->filter()->values();
            }

            $options = ['size' => ['1536x1024', '1792x1024', '1024x1024']];
            return $prisma->ensure( 'image' )->image( $args['prompt'], $files->toArray(), $options )->base64();
        }
        catch( PrismaException $e )
        {
            throw new Error( $e->getMessage(), null, null, null, null, null, $e->getTrace() );
        }
    }
}

<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Prism\Prism\Prism;
use Prism\Prism\ValueObjects\Media\Audio;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Media\Video;
use Prism\Prism\ValueObjects\Media\Document;
use Prism\Prism\Exceptions\PrismException;
use Aimeos\Cms\Models\File;
use GraphQL\Error\Error;


final class Imagine
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ): array
    {
        if( empty( $args['prompt'] ) ) {
            throw new Error( 'Prompt must not be empty' );
        }

        $files = collect();
        $input = $args['prompt'];
        $prompt = join( "\n\n", array_filter( [
            view( 'cms::prompts.imagine' )->render(),
            $args['context'] ?? '',
            $input
        ] ) );

        $provider = config( 'cms.ai.image' ) ?: 'gemini';
        $model = config( 'cms.ai.image-model' ) ?: 'gemini-2.5-flash-image-preview';

        try
        {
            $prism = Prism::image()->using( $provider, $model )
                ->withMaxTokens( config( 'cms.ai.maxtoken', 32768 ) )
                ->withClientOptions( [
                    'timeout' => 60,
                    'connect_timeout' => 10,
                ] );

            if( !empty( $ids = $args['files'] ?? null ) )
            {
                $files = File::where( 'id', $ids )->get()->map( function( $file ) {

                    if( str_starts_with( $file->path, 'http' ) )
                    {
                        return match( explode( '/', $file->mime )[0] ) {
                            'image' => Image::fromUrl( $file->path, $file->mime ),
                            'audio' => Audio::fromUrl( $file->path, $file->mime ),
                            'video' => Video::fromUrl( $file->path, $file->mime ),
                            default => Document::fromUrl( $file->path, $file->mime ),
                        };
                    }

                    $disk = config( 'cms.disk', 'public' );

                    return match( explode( '/', $file->mime )[0] ) {
                        'image' => Image::fromStoragePath( $file->path, $disk ),
                        'audio' => Audio::fromStoragePath( $file->path, $disk ),
                        'video' => Video::fromStoragePath( $file->path, $disk ),
                        default => Document::fromStoragePath( $file->path, $disk ),
                    };
                } )->values();
            }

            $prism->whenProvider( 'openai', fn( $request ) => $request->withProviderOptions( [
                'size' => match( $model ) {
                    'gpt-image-1' => '1536x1024',
                    'dall-e-3' => '1792x1024',
                    'dall-e-2' => '1024x1024',
                    default => 'auto',
                }
            ] ) );

            $response = $prism->withPrompt( $prompt, $files->toArray() )->generate();

            $prompt = collect( $response->images )
                ->map( fn( $image ) => $image->revisedPrompt )
                ->filter()
                ->first() ?? $input;

            $images = collect( $response->images )
                ->map( fn( $image ) => $image->base64() )
                ->toArray();

            return array_merge( [$prompt], $images );
        }
        catch( PrismException $e )
        {
            throw new Error( $e->getMessage() );
        }
    }
}

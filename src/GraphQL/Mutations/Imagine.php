<?php

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

        $provider = config( 'cms.ai.image' ) ?: 'openai';
        $model = config( 'cms.ai.image-model' ) ?: 'dall-e-3';

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
                            'image' => Image::fromUrl( $file->path ),
                            'audio' => Audio::fromUrl( $file->path ),
                            'video' => Video::fromUrl( $file->path ),
                            default => Document::fromUrl( $file->path ),
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

            $response = $prism->withPrompt( $prompt, $files->toArray() )
                ->whenProvider( 'openai', fn( $request ) => $request
                    ->withProviderOptions( [
                        'image' => $files->first()?->base64(),
                        'size' => match( $model ) {
                            'gpt-image-1' => '1536x1024',
                            'dall-e-3' => '1792x1024',
                            'dall-e-2' => '1024x1024',
                            default => 'auto',
                        }
                    ] )
                )
                ->generate();

            $prompt = collect( $response->images )
                ->map( fn( $image ) => $image->hasRevisedPrompt() ? $image->revisedPrompt : null )
                ->filter()
                ->first() ?? $input;

            $images = collect( $response->images )
                ->map( fn( $image ) => $image->base64 ?? Image::fromUrl( $image->url )->base64() )
                ->filter()
                ->toArray();

            return array_merge( [$prompt], $images );
        }
        catch( PrismException $e )
        {
            throw new Error( $e->getMessage() );
        }
    }
}

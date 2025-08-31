<?php

namespace Aimeos\Cms\GraphQL\Mutations;

use Prism\Prism\Prism;
use Prism\Prism\ValueObjects\Media\Audio;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Media\Video;
use Prism\Prism\ValueObjects\Media\Document;
use Prism\Prism\ValueObjects\ProviderTool;
use Prism\Prism\Exceptions\PrismException;
use Aimeos\Cms\Models\File;
use GraphQL\Error\Error;


final class Compose
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ): string
    {
        if( empty( $args['prompt'] ) ) {
            throw new Error( 'Prompt must not be empty' );
        }

        $files = [];
        $provider = config( 'cms.ai.text' ) ?: 'gemini';
        $model = config( 'cms.ai.text-model' ) ?: 'gemini-2.5-flash';

        try
        {
            $prism = Prism::text()->using( $provider, $model )
                ->withMaxTokens( config( 'cms.ai.maxtoken', 32768 ) )
                ->withSystemPrompt( view( 'cms::prompts.compose' )->render() . "\n" . ($args['context'] ?? '') )
                ->whenProvider( 'gemini', fn( $request ) => $request->withProviderTools( [
                    new ProviderTool( 'google_search' )
                ] ) )
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
                } )->values()->toArray();
            }

            $response = $prism->withPrompt( $args['prompt'], $files )->asText();

            return $response->text;
        }
        catch( PrismException $e )
        {
            throw new Error( $e->getMessage() );
        }
    }
}

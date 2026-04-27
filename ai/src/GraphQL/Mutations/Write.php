<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\File;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Media\Audio;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Media\Video;
use Prism\Prism\ValueObjects\Media\Document;
use Prism\Prism\ValueObjects\ProviderTool;
use Prism\Prism\Exceptions\PrismException;
use Illuminate\Support\Facades\Log;
use GraphQL\Error\Error;


final class Write
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
        $provider = config( 'cms.ai.write.provider' );
        $config = config( 'cms.ai.write', [] );
        $model = config( 'cms.ai.write.model' );

        try
        {
            $system = view( 'cms::prompts.write' )->render() . "\n" . ( $args['context'] ?? '' );

            $prism = Prism::text()->using( $provider, $model, $config )
                ->withMaxTokens( config( 'cms.ai.maxtoken', 32768 ) )
                ->withSystemPrompt( $system )
                ->whenProvider( 'gemini', fn( $request ) => $request->withProviderTools( [
                    new ProviderTool( 'google_search' )
                ] ) )
                ->withClientOptions( [
                    'timeout' => 60,
                    'connect_timeout' => 10,
                ] );

            if( !empty( $args['files'] ) )
            {
                $disk = config( 'cms.disk', 'public' );

                foreach( File::whereIn( 'id', $args['files'] )->select( 'id', 'path', 'mime' )->get() as $file )
                {
                    $type = explode( '/', $file->mime, 2 )[0];

                    if( str_starts_with( (string) $file->path, 'http' ) )
                    {
                        $files[] = match( $type ) {
                            'image' => Image::fromUrl( (string) $file->path ),
                            'audio' => Audio::fromUrl( (string) $file->path ),
                            'video' => Video::fromUrl( (string) $file->path ),
                            default => Document::fromUrl( (string) $file->path ),
                        };
                    }
                    else
                    {
                        $files[] = match( $type ) {
                            'image' => Image::fromStoragePath( (string) $file->path, $disk ),
                            'audio' => Audio::fromStoragePath( (string) $file->path, $disk ),
                            'video' => Video::fromStoragePath( (string) $file->path, $disk ),
                            default => Document::fromStoragePath( (string) $file->path, $disk ),
                        };
                    }
                }
            }

            $response = $prism->withPrompt( $args['prompt'], $files )->asText();

            return $response->text;
        }
        catch( PrismException $e )
        {
            Log::error( 'AI service error', ['mutation' => 'Write', 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()] );
            throw new Error( config( 'app.debug' ) ? $e->getMessage() : 'AI service error', null, null, null, null, $e );
        }
    }
}

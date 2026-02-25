<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Permission;
use Aimeos\Cms\Models\File;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Media\Audio;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Media\Video;
use Prism\Prism\ValueObjects\Media\Document;
use Prism\Prism\ValueObjects\ProviderTool;
use Prism\Prism\Exceptions\PrismException;
use Illuminate\Support\Facades\Auth;
use GraphQL\Error\Error;


final class Write
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ): string
    {
        if( !Permission::can( 'text:write', Auth::user() ) ) {
            throw new Error( 'Insufficient permissions' );
        }

        if( empty( $args['prompt'] ) ) {
            throw new Error( 'Prompt must not be empty' );
        }

        $files = [];
        $provider = config( 'cms.ai.write.provider' );
        $model = config( 'cms.ai.write.model' );

        try
        {
            /** @phpstan-ignore-next-line argument.type */
            $system = view( 'cms::prompts.write' )->render() . "\n" . ( $args['context'] ?? '' );

            $prism = Prism::text()->using( $provider, $model )
                ->withMaxTokens( config( 'cms.ai.maxtoken', 32768 ) )
                ->withSystemPrompt( $system )
                ->whenProvider( 'gemini', fn( $request ) => $request->withProviderTools( [
                    new ProviderTool( 'google_search' )
                ] ) )
                ->withClientOptions( [
                    'timeout' => 60,
                    'connect_timeout' => 10,
                ] );

            if( !empty( $ids = $args['files'] ?? null ) )
            {
                $files = File::whereIn( 'id', $ids )->get()->map( function( $file ) {

                    if( str_starts_with( (string) $file->path, 'http' ) )
                    {
                        return match( explode( '/', $file->mime )[0] ) {
                            'image' => Image::fromUrl( (string) $file->path ),
                            'audio' => Audio::fromUrl( (string) $file->path ),
                            'video' => Video::fromUrl( (string) $file->path ),
                            default => Document::fromUrl( (string) $file->path ),
                        };
                    }

                    $disk = config( 'cms.disk', 'public' );

                    return match( explode( '/', $file->mime )[0] ) {
                        'image' => Image::fromStoragePath( (string) $file->path, $disk ),
                        'audio' => Audio::fromStoragePath( (string) $file->path, $disk ),
                        'video' => Video::fromStoragePath( (string) $file->path, $disk ),
                        default => Document::fromStoragePath( (string) $file->path, $disk ),
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

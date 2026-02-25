<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Permission;
use Aimeos\Cms\Models\File;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Enums\ToolChoice;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\ValueObjects\Media\Audio;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Media\Video;
use Prism\Prism\ValueObjects\Media\Document;
use Prism\Prism\ValueObjects\ProviderTool;
use Illuminate\Support\Facades\Auth;
use GraphQL\Error\Error;


final class Synthesize
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ): string
    {
        if( !Permission::can( 'page:synthesize', Auth::user() ) ) {
            throw new Error( 'Insufficient permissions' );
        }

        if( empty( $args['prompt'] ) ) {
            throw new Error( 'Prompt must not be empty' );
        }

        /** @phpstan-ignore-next-line argument.type */
        $system = view( 'cms::prompts.synthesize' )->render() . "\n" . view( 'cms::prompts.compose' )->render() . "\n";

        $files = [];
        $provider = config( 'cms.ai.write.provider' );
        $model = config( 'cms.ai.write.model' );

        try
        {
            $prism = Prism::text()->using( $provider, $model )
                ->withMaxTokens( config( 'cms.ai.maxtoken', 32768 ) )
                ->withSystemPrompt( $system . "\n" . ($args['context'] ?? '') )
                ->withTools( \Aimeos\Cms\Tools::get() )
                ->withToolChoice( ToolChoice::Any )
                ->withMaxSteps( 10 );

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

            $msg = 'Done';
            $msg .= "\n---\n" . join( "\n", $this->trace( $prism->withPrompt( $args['prompt'], $files )->asText() ) );
        }
        catch( PrismException $e )
        {
            throw new Error( $e->getMessage() );
        }
        catch( \Exception $e )
        {
            $msg = match( get_class( $ex = $e->getPrevious() ?? $e ) )
            {
                'Illuminate\Database\UniqueConstraintViolationException' => 'Already exists',
                default => $ex->getMessage(),
            };
        }

        return $msg . "\n";
    }


    /**
     * Returns a list of tool calls made during the execution of the Prism response for debugging purposes.
     *
     * @param \Prism\Prism\Text\Response $response
     * @return list<string>
     */
    protected function trace( \Prism\Prism\Text\Response $response ) : array
    {
        $msgs = [];

        foreach( $response->steps as $step )
        {
            if( $step->toolCalls )
            {
                foreach( $step->toolCalls as $toolCall )
                {
                    $args = $toolCall->arguments();

                    foreach( $args as $key => $value )
                    {
                        $args[$key] = is_string( $value ) && mb_strlen( $value ) > 60
                            ? mb_substr( $value, 0, 60 ) . ' ...'
                            : $value;
                    }

                    $msgs[] = $toolCall->name . '(' . ( empty( $args ) ? '' : json_encode( $args, JSON_PRETTY_PRINT ) ) . ')';
                }
            }
        }

        return $msgs;
    }
}

<?php

namespace Aimeos\Cms\GraphQL\Mutations;

use Prism\Prism\Prism;
use Prism\Prism\Enums\ToolChoice;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\ValueObjects\Media\Audio;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Media\Video;
use Prism\Prism\ValueObjects\Media\Document;
use Prism\Prism\ValueObjects\ProviderTool;
use Aimeos\Cms\Models\File;
use GraphQL\Error\Error;


final class Synthesize
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

        $system = view( 'cms::prompts.synthesize' )->render() . "\n"
            . view( 'cms::prompts.compose' )->render() . "\n";

        $files = [];
        $provider = config( 'cms.ai.text' ) ?: 'gemini';
        $model = config( 'cms.ai.text-model' ) ?: 'gemini-2.5-flash';

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

            $msg = 'Done';
            $msg .= "\n---\n" . join( "\n", $this->trace( $prism->withPrompt( $args['prompt'], $files )->asText() ) );
        }
        catch( PrismException $e )
        {
            throw new Error( $e->getMessage() );
        }
        catch( \Exception $e )
        {
            switch( get_class( $ex = $e->getPrevious() ?? $e ) )
            {
                case 'Illuminate\Database\UniqueConstraintViolationException':
                    $msg = 'Already exists';
                default:
                    $msg = $ex->getMessage();
            }
        }

        return $msg . "\n";
    }


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

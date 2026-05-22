<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\File;
use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Tools;
use Aimeos\Prisma\Exceptions\PrismaException;
use Illuminate\Support\Facades\Log;
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

        $system = view( 'cms::prompts.synthesize' )->render() . "\n" . view( 'cms::prompts.write' )->render() . "\n";

        $files = [];
        $provider = config( 'cms.ai.write.provider' );
        $config = config( 'cms.ai.write', [] );
        $model = config( 'cms.ai.write.model' );

        try
        {
            if( !empty( $args['files'] ) )
            {
                $disk = config( 'cms.disk', 'public' );

                foreach( File::whereIn( 'id', $args['files'] )->select( 'id', 'path', 'mime' )->get() as $file )
                {
                    $files[] = str_starts_with( (string) $file->path, 'http' )
                        ? \Aimeos\Prisma\Files\File::fromUrl( (string) $file->path, $file->mime )
                        : \Aimeos\Prisma\Files\File::fromStoragePath( (string) $file->path, $disk, $file->mime );
                }
            }

            $response = Prisma::text()
                ->using( $provider, $config )
                ->model( $model )
                ->withMaxTokens( config( 'cms.ai.maxtoken' ) )
                ->withSystemPrompt( $system . "\n" . ( $args['context'] ?? '' ) )
                ->withTools( array_merge( \Aimeos\Cms\Tools::get(), [
                    Tools::provider( 'web_search' ),
                    Tools::provider( 'web_fetch' ),
                ] ) )
                ->withToolChoice( \Aimeos\Prisma\Providers\Base::REQ )
                ->withMaxSteps( 10 )
                ->ensure( 'write' )
                ->write( $args['prompt'], $files, $config ); // @phpstan-ignore-line method.notFound

            $msg = 'Done';
            $msg .= "\n---\n" . join( "\n", $this->trace( $response ) );
        }
        catch( PrismaException $e )
        {
            Log::error( 'AI service error', ['mutation' => 'Synthesize', 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()] );
            throw new Error( config( 'app.debug' ) ? $e->getMessage() : 'AI service error', null, null, null, null, $e );
        }
        catch( \Exception $e )
        {
            Log::error( 'Synthesize error', ['message' => $e->getMessage()] );

            $msg = match( get_class( $ex = $e->getPrevious() ?? $e ) )
            {
                'Illuminate\Database\UniqueConstraintViolationException' => 'Already exists',
                default => 'An unexpected error occurred',
            };
        }

        return $msg . "\n";
    }


    /**
     * Returns a list of tool calls made during the execution for debugging purposes.
     *
     * @param \Aimeos\Prisma\Responses\TextResponse $response
     * @return list<string>
     */
    protected function trace( \Aimeos\Prisma\Responses\TextResponse $response ) : array
    {
        $msgs = [];

        foreach( $response->steps() as $step )
        {
            $args = $step->arguments();

            foreach( $args as $key => $value )
            {
                $args[$key] = is_string( $value ) && mb_strlen( $value ) > 60
                    ? mb_substr( $value, 0, 60 ) . ' ...'
                    : $value;
            }

            $msgs[] = $step->name() . '(' . ( empty( $args ) ? '' : json_encode( $args, JSON_PRETTY_PRINT ) ) . ')';
        }

        return $msgs;
    }
}

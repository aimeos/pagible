<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Concerns\ObservesPrisma;
use Aimeos\Cms\Permission;
use Aimeos\Prisma\Prisma;
use Aimeos\Cms\Models\File;
use Aimeos\Prisma\Tools;
use Aimeos\Prisma\Exceptions\PrismaException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use GraphQL\Error\Error;


final class Write
{
    use ObservesPrisma;


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
        $limit = (int) ini_get( 'max_execution_time' );

        set_time_limit( (int) config( 'cms.ai.timeout' ) ); // long AI call; lift PHP's default 30s execution limit

        try
        {
            $system = view( 'cms::prompts.write' )->render() . "\n" . ( $args['context'] ?? '' );

            if( !empty( $args['files'] ) )
            {
                if( !Permission::can( 'file:view', Auth::user() ) ) {
                    throw new Error( 'Insufficient permissions' );
                }

                foreach( File::whereIn( 'id', $args['files'] )->select( 'id', 'tenant_id', 'disk', 'path', 'mime' )->get() as $file )
                {
                    $files[] = str_starts_with( (string) $file->path, 'http' )
                        ? \Aimeos\Prisma\Files\File::fromUrl(
                            (string) $file->path,
                            $file->mime,
                            !(bool) config( 'cms.allow-internal' ),
                        )
                        : \Aimeos\Prisma\Files\File::fromStoragePath(
                            (string) $file->path,
                            File::diskName( (string) $file->disk ),
                            $file->mime,
                        );
                }
            }

            return Prisma::text()->observe( $this->observer() )
                ->using( $provider, $config )
                ->model( $model )
                ->withMaxTokens( config( 'cms.ai.maxtoken' ) )
                ->withSystemPrompt( $system )
                ->withTools( [Tools::provider( 'web_search' ), Tools::provider( 'web_fetch' )] )
                ->ensure( 'write' )
                ->write( $args['prompt'], $files, $config ) // @phpstan-ignore-line method.notFound
                ->text();
        }
        catch( PrismaException $e )
        {
            Log::error( 'AI service error', ['mutation' => 'Write', 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()] );
            throw new Error( $e->getMessage(), null, null, null, null, $e );
        }
        finally
        {
            set_time_limit( $limit );
        }
    }
}

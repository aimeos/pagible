<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Concerns\ObservesPrisma;
use Aimeos\Prisma\Prisma;
use Aimeos\Cms\JsonSchema;
use Aimeos\Cms\Refiner;
use Aimeos\Cms\Tools as CmsTools;
use Aimeos\Cms\Validation;
use Aimeos\Prisma\Schema\Schema;
use Aimeos\Prisma\Tools;
use Aimeos\Prisma\Exceptions\PrismaException;
use Illuminate\Support\Facades\Log;
use GraphQL\Error\Error;


final class Refine
{
    use ObservesPrisma;


    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array<mixed>
     */
    public function __invoke( $rootValue, array $args ): array
    {
        if( empty( $args['prompt'] ) ) {
            throw new Error( 'Prompt must not be empty' );
        }

        $provider = config( 'cms.ai.refine.provider' );
        $config = config( 'cms.ai.refine', [] );
        $model = config( 'cms.ai.refine.model' );

        $system = view( 'cms::prompts.refine' )->render();
        $type = $args['type'] ?? 'content';
        $content = $args['content'] ?: [];

        try
        {
            set_time_limit( (int) config( 'cms.ai.timeout' ) ); // long AI call; lift PHP's default 30s execution limit (matches client timeout)

            $schema = Schema::fromArray( 'response', JsonSchema::build( $type, $args['pagetype'] ?? null ) );
            $response = Prisma::text()->observe( $this->observer() )
                ->using( $provider, $config )
                ->model( $model )
                ->withClientOptions( ['timeout' => (int) config( 'cms.ai.timeout' )] )
                ->withMaxTokens( config( 'cms.ai.maxtoken' ) )
                ->withSystemPrompt( $system . "\n" . ($args['context'] ?? '') . ( !empty( $args['lang'] ) ? "\nWrite the content in language: " . $args['lang'] : '' ) )
                ->withTools( [
                    Tools::laravel( CmsTools\GetPage::class ),
                    Tools::laravel( CmsTools\GetPageHistory::class ),
                    Tools::laravel( CmsTools\GetPageMetrics::class ),
                    Tools::laravel( CmsTools\GetPageTree::class ),
                    Tools::laravel( CmsTools\SearchPages::class ),

                    Tools::laravel( CmsTools\AddElement::class ),
                    Tools::laravel( CmsTools\DropElement::class ),
                    Tools::laravel( CmsTools\GetElement::class ),
                    Tools::laravel( CmsTools\RestoreElement::class ),
                    Tools::laravel( CmsTools\SaveElement::class ),

                    Tools::laravel( CmsTools\AddFile::class ),
                    Tools::laravel( CmsTools\DropFile::class ),
                    Tools::laravel( CmsTools\GetFile::class ),
                    Tools::laravel( CmsTools\RestoreFile::class ),
                    Tools::laravel( CmsTools\SaveFile::class ),

                    Tools::provider( 'web_search' ),
                    Tools::provider( 'web_fetch' )
                ] )
                ->ensure( 'structure' )
                ->structure( $args['prompt'] . "\n\nContent as JSON:\n" . json_encode( $content ), $schema, [], ['mode' => 'json'] ); // @phpstan-ignore-line method.notFound

            $structured = $response->structured();

            if( !$structured ) {
                throw new Error( 'No structured content returned in refine response' );
            }

            if( $errors = $schema->validate( $structured ) ) {
                Log::warning( 'Invalid refine response', ['mutation' => 'Refine', 'errors' => $errors] );
                throw new Error( config( 'app.debug' ) ? 'Invalid refine response: ' . implode( '; ', $errors ) : 'Invalid content in refine response' );
            }

            if( $type !== 'content' )
            {
                $items = [];

                foreach( $structured as $key => $data )
                {
                    if( is_array( $data ) ) {
                        $items[$key] = array_filter( $data, fn( $v ) => $v !== null );
                    }
                }

                return (array) Validation::structured( $items, $type, $content, $args['pagetype'] ?? null );
            }

            return Refiner::merge( $content, $structured['contents'] ?? [], $args['pagetype'] ?? null );
        }
        catch( PrismaException $e )
        {
            Log::error( 'AI service error', ['mutation' => 'Refine', 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()] );
            throw new Error( $e->getMessage(), null, null, null, null, $e );
        }
    }
}

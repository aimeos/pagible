<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\JsonSchema;
use Aimeos\Cms\Refiner;
use Aimeos\Cms\Tools as CmsTools;
use Aimeos\Cms\Validation;
use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Schema\Schema;
use Aimeos\Prisma\Tools;
use Aimeos\Prisma\Exceptions\PrismaException;
use Illuminate\Support\Facades\Log;
use GraphQL\Error\Error;


final class Refine
{
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
            $response = Prisma::text()->using( $provider, $config )
                ->model( $model )
                ->withClientOptions( ['timeout' => 300] )
                ->withMaxTokens( config( 'cms.ai.maxtoken' ) )
                ->withSystemPrompt( $system . "\n" . ($args['context'] ?? '') . ( !empty( $args['lang'] ) ? "\nWrite the content in language: " . $args['lang'] : '' ) )
                ->withTools( [
                    Tools::laravel( CmsTools\GetPage::class )->max( 1 ),
                    Tools::laravel( CmsTools\GetPageTree::class )->max( 1 ),
                    Tools::laravel( CmsTools\SearchPages::class )->max( 3 ),
                    Tools::provider( 'web_search' ),
                    Tools::provider( 'web_fetch' )
                ] )
                ->ensure( 'structure' )
                ->structure( $args['prompt'] . "\n\nContent as JSON:\n" . json_encode( $content ), Schema::fromArray( 'response', JsonSchema::build( $type, $args['pagetype'] ?? null ) ) ); // @phpstan-ignore-line method.notFound

            $structured = $response->structured();

            if( !$structured ) {
                throw new Error( 'No structured content returned in refine response' );
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
            throw new Error( config( 'app.debug' ) ? $e->getMessage() : 'AI service error', null, null, null, null, $e );
        }
    }
}

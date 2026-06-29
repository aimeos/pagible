<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Concerns\Watch;
use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Exceptions\PrismaException;
use Illuminate\Support\Facades\Log;
use GraphQL\Error\Error;


final class Translate
{
    use Watch;


    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array<int, mixed>
     */
    public function __invoke( $rootValue, array $args ): array
    {
        if( empty( $texts = $args['texts'] ) ) {
            throw new Error( 'Input texts must not be empty' );
        }

        if( empty( $to = $args['to'] ) ) {
            throw new Error( 'Target language must not be empty' );
        }

        $provider = config( 'cms.ai.translate.provider' );
        $config = config( 'cms.ai.translate', [] );
        $model = config( 'cms.ai.translate.model' );

        $config += [
            'ignore_tags' => ['x'],
            'tag_handling' => 'xml',
            'preserve_formatting' => true,
            'model_type' => 'prefer_quality_optimized',
        ];

        try
        {
            return Prisma::type( 'text' )
                ->observe( $this->observer() )
                ->using( $provider, $config )
                ->model( $model )
                ->ensure( 'translate' )
                ->translate( $texts, $to, $args['from'] ?? null, $args['context'] ?? null, $config ) // @phpstan-ignore-line method.notFound
                ->texts();
        }
        catch( PrismaException $e )
        {
            Log::error( 'AI service error', ['mutation' => 'Translate', 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()] );
            throw new Error( config( 'app.debug' ) ? $e->getMessage() : 'AI service error', null, null, null, null, $e );
        }
    }
}

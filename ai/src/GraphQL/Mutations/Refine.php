<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Utils;
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
     * @return array<int, mixed>
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
                ->withMaxTokens( config( 'cms.ai.maxtoken' ) )
                ->withSystemPrompt( $system . "\n" . ($args['context'] ?? '') )
                ->withTools( [Tools::provider( 'web_search' ), Tools::provider( 'web_fetch' )] )
                ->withClientOptions( [
                    'timeout' => 180,
                    'connect_timeout' => 10,
                ] )
                ->ensure( 'structure' )
                ->structure( $args['prompt'] . "\n\nContent as JSON:\n" . json_encode( $content ), $this->schema( $type ) ); // @phpstan-ignore-line method.notFound

            $structured = $response->structured();

            if( !$structured ) {
                throw new Error( 'Invalid content in refine response' );
            }

            return $this->merge( $content, $structured['contents'] ?? [] );
        }
        catch( PrismaException $e )
        {
            Log::error( 'AI service error', ['mutation' => 'Refine', 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()] );
            throw new Error( config( 'app.debug' ) ? $e->getMessage() : 'AI service error', null, null, null, null, $e );
        }
    }


    /**
     * Merges the existing content with the response from the AI
     *
     * @param array<mixed> $content Existing content elements
     * @param array<mixed> $response AI response with updated text content
     * @return array<mixed> Updated content elements
     */
    protected function merge( array $content, array $response ) : array
    {
        $result = [];
        $map = collect( $content )->keyBy( 'id' );

        foreach( $response as $item )
        {
            $entry = (array) $map->pull( $item['id'], [] );
            $entry['data'] = (array) ( $entry['data'] ?? [] );
            $entry['type'] = $item['type'] ?? ( $entry['type'] ?? 'text' );

            if( !isset( $entry['id'] ) ) {
                $entry['id'] = Utils::uid();
            }

            foreach( $item['data'] ?? [] as $data )
            {
                if( empty( $data['name'] ) ) {
                    continue;
                }

                $m = [];

                if( $entry['type'] === 'heading' && preg_match( '/^(#+)(.*)$/', (string) ($data['value'] ?? ''), $m ) )
                {
                    $entry['data'][$data['name']] = trim( $m[2] );
                    $entry['data']['level'] = (string) strlen( $m[1] );
                }
                else
                {
                    $entry['data'][$data['name']] = (string) ($data['value'] ?? '');
                }
            }

            $result[] = $entry;
        }

        return $result;
    }


    /**
     * Returns the schema for the content elements
     *
     * @param string $type The type of content elements
     * @return Schema The schema for the content elements
     */
    protected function schema( string $type ) : Schema
    {
        $types = array_keys( \Aimeos\Cms\Schema::schemas( section: $type ) );

        return Schema::for( 'response', [
            'contents' => Schema::array()->description( 'List of page content elements' )->required()->items(
                Schema::object( [
                    'id' => Schema::string()->description( 'The ID of the content element' )->nullable()->required(),
                    'type' => Schema::string()->description( 'The type of the content element' )->enum( $types )->required(),
                    'data' => Schema::array()->description( 'List of texts for the content element' )->required()->items(
                        Schema::object( [
                            'name' => Schema::string()->description( 'Name of the text element' )->enum( ['title', 'text'] )->required(),
                            'value' => Schema::string()->description( 'Plain title, markdown text or source code text' )->required(),
                        ] )
                    ),
                ] )
            ),
        ] );
    }
}

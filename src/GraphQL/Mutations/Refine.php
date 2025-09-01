<?php

namespace Aimeos\Cms\GraphQL\Mutations;

use Prism\Prism\Prism;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Exceptions\PrismException;
use Aimeos\Cms\Models\File;
use GraphQL\Error\Error;


final class Refine
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ): array
    {
        if( empty( $args['prompt'] ) ) {
            throw new Error( 'Prompt must not be empty' );
        }

        $provider = config( 'cms.ai.struct' ) ?: 'gemini';
        $model = config( 'cms.ai.struct-model' ) ?: 'gemini-2.5-flash';

        $system = view( 'cms::prompts.refine' )->render();
        $type = $args['type'] ?? 'content';
        $content = $args['content'] ?: [];

        try
        {
            $response = Prism::structured()->using( $provider, $model )
                ->withMaxTokens( config( 'cms.ai.maxtoken', 32768 ) )
                ->withSystemPrompt( $system . "\n" . ($args['context'] ?? '') )
                ->withPrompt( $args['prompt'] . "\n\nContent as JSON:\n" . json_encode( $content ) )
                ->withProviderOptions( ['use_tool_calling' => true] )
                ->withSchema( $this->schema( $type ) )
                ->withClientOptions( [
                    'timeout' => 180,
                    'connect_timeout' => 10,
                ] )
                ->asStructured();

            if( !$response->structured ) {
                throw new Error( 'Invalid content in refine response' );
            }

            return $this->merge( $content, $response->structured['contents'] ?? [] );
        }
        catch( PrismException $e )
        {
            throw new Error( $e->getMessage() );
        }
    }


    /**
     * Merges the existing content with the response from the AI
     *
     * @param array $content Existing content elements
     * @param array $response AI response with updated text content
     * @return array Updated content elements
     */
    protected function merge( array $content, array $response ) : array
    {
        $result = [];
        $map = collect( $content )->keyBy( 'id' );

        foreach( $response as $item )
        {
            $entry = (array) $map->get( $item['id'], [] );
            $entry['data'] = (array) ( $entry['data'] ?? [] );
            $entry['type'] = $item['type'] ?? ( $entry['type'] ?? 'text' );

            foreach( $item['data'] ?? [] as $data )
            {
                if( empty( $data['name'] ) ) {
                    continue;
                }

                $m = [];

                if( $entry['type'] === 'heading' && preg_match( '/^(#+)(.*)$/', (string) $data['value'] ?? '', $m ) )
                {
                    $entry['data'][$data['name']] = trim( $m[2] );
                    $entry['data']['level'] = (string) strlen( $m[1] );
                }
                else
                {
                    $entry['data'][$data['name']] = (string) ( $data['value'] ?? '' );
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
     * @return ObjectSchema The schema for the content elements
     */
    protected function schema( string $type ) : ObjectSchema
    {
        $types = collect( config( "cms.schemas.$type", [] ) )->keys()->all();

        return new ObjectSchema(
            name: 'response',
            description: 'The content response',
            properties: [
                new ArraySchema(
                    name: 'contents',
                    description: 'List of page content elements',
                    items: new ObjectSchema(
                        name: 'content',
                        description: 'A content element',
                        properties: [
                            new StringSchema( 'id', 'The ID of the content element', nullable: true ),
                            new EnumSchema( 'type', 'The type of the content element', options: $types ),
                            new ArraySchema(
                                name: 'data',
                                description: 'List of texts for the content element',
                                items: new ObjectSchema(
                                    name: 'text',
                                    description: 'A text of the content element',
                                    properties: [
                                        new EnumSchema( 'name', 'Name of the text element', options: ['title', 'text'] ),
                                        new StringSchema( 'value', 'Plain title, markdown text or source code text' ),
                                    ],
                                    requiredFields: ['name', 'value']
                                )
                            )
                        ],
                        requiredFields: ['id', 'type', 'data']
                    )
                )
            ],
            requiredFields: ['contents']
        );
    }
}

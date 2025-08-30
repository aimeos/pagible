<?php

namespace Aimeos\Cms\GraphQL\Mutations;

use Prism\Prism\Prism;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Aimeos\Cms\GraphQL\Exception;
use Aimeos\Cms\Models\File;


final class Refine
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ): array
    {
        if( empty( $args['prompt'] ) ) {
            throw new Exception( 'Prompt must not be empty' );
        }

        $provider = config( 'cms.ai.text' ) ?: 'gemini';
        $model = config( 'cms.ai.text-model' ) ?: 'gemini-2.5-flash';

        $system = view( 'cms::prompts.refine' )->render();
        $type = $args['type'] ?? 'content';
        $content = $args['content'] ?: [];

        $response = Prism::structured()->using( $provider, $model )
            ->withSystemPrompt( $system . "\n" . ($args['context'] ?? '') )
            ->withPrompt( $args['prompt'] . "\n\nContent as JSON:\n" . json_encode( $content ) )
            ->withSchema( $this->schema( $type ) )
            ->asStructured();

        if( !$response->structured ) {
            throw new Exception( 'Invalid content in refine response' );
        }

        return $this->merge( $content, $response->structured );
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
                if( !empty( $data['name'] ) ) {
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
     * @return ArraySchema The schema for the content elements
     */
    protected function schema( string $type ) : ArraySchema
    {
        $types = collect( config( "cms.schema.$type", [] ) )->keys()->all();

        return new ArraySchema(
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
                requiredFields: ['type', 'data']
            )
        );
    }
}

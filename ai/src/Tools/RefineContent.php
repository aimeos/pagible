<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Utils;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Models\Page;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Exceptions\PrismException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('refine-content')]
#[Title('Refine page content using AI')]
#[Description('Improves or restructures existing page content using AI based on a prompt. Pass the page ID and a prompt describing the changes. Returns the refined content elements as a JSON array.')]
class RefineContent extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'page:refine', $request->user() ) ) {
            throw new \Exception( 'Insufficient permissions' );
        }

        $validated = $request->validate([
            'id' => 'required|string|max:36',
            'prompt' => 'required|string|max:2000',
            'context' => 'string|max:30000',
        ], [
            'id.required' => 'You must specify the ID of the page to refine.',
            'prompt.required' => 'You must provide a prompt describing how to refine the content.',
        ] );

        /** @var Page|null $page */
        $page = Page::withTrashed()->select( 'id', 'content', 'latest_id' )
            ->with( ['latest' => fn( $q ) => $q->select( 'id', 'versionable_id', 'aux' )] )
            ->find( $validated['id'] );

        if( !$page ) {
            return Response::structured( ['error' => 'Page not found.'] );
        }

        $content = (array) ( $page->latest?->aux->content ?? $page->content ?? [] );

        $provider = config( 'cms.ai.refine.provider' );
        $config = config( 'cms.ai.refine', [] );
        $model = config( 'cms.ai.refine.model' );

        $system = view( 'cms::prompts.refine' )->render();
        $types = array_keys( \Aimeos\Cms\Schema::schemas( section: 'content' ) );

        try
        {
            $response = Prism::structured()->using( $provider, $model, $config )
                ->withMaxTokens( config( 'cms.ai.maxtoken', 32768 ) )
                ->withSystemPrompt( $system . "\n" . ( $validated['context'] ?? '' ) )
                ->withPrompt( $validated['prompt'] . "\n\nContent as JSON:\n" . json_encode( $content ) )
                ->withProviderOptions( ['use_tool_calling' => true] )
                ->withSchema( $this->schema_response( $types ) )
                ->withClientOptions( [
                    'timeout' => 180,
                    'connect_timeout' => 10,
                ] )
                ->asStructured();

            if( !$response->structured ) {
                return Response::structured( ['error' => 'Invalid content in refine response.'] );
            }

            $result = $this->merge( $content, $response->structured['contents'] ?? [] );

            return Response::structured( ['content' => $result] );
        }
        catch( PrismException $e )
        {
            throw new \Exception( $e->getMessage() );
        }
    }


    /**
     * Merges the existing content with the response from the AI.
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

                if( $entry['type'] === 'heading' && preg_match( '/^(#+)(.*)$/', (string) @$data['value'], $m ) )
                {
                    $entry['data'][$data['name']] = trim( $m[2] );
                    $entry['data']['level'] = (string) strlen( $m[1] );
                }
                else
                {
                    $entry['data'][$data['name']] = (string) @$data['value'];
                }
            }

            $result[] = $entry;
        }

        return $result;
    }


    /**
     * Returns the schema for the AI structured response.
     *
     * @param array<string> $types Available content element types
     * @return ObjectSchema
     */
    protected function schema_response( array $types ) : ObjectSchema
    {
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


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return [
            'id' => $schema->string()
                ->description('The UUID of the page whose content to refine.')
                ->required(),
            'prompt' => $schema->string()
                ->description('Describe how to improve the content, e.g., "Make the text more engaging and add subheadings" or "Rewrite for a technical audience".')
                ->required(),
            'context' => $schema->string()
                ->description('Additional context such as target audience, tone, or brand guidelines.'),
        ];
    }


    /**
     * Determine if the tool should be registered.
     *
     * @param Request $request The incoming request to check permissions for.
     * @return bool TRUE if the tool should be registered, FALSE otherwise.
     */
    public function shouldRegister( Request $request ) : bool
    {
        return Permission::can( 'page:refine', $request->user() );
    }
}

<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Permission;
use Aimeos\Cms\Models\Element;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[IsReadOnly]
#[Name('get-element')]
#[Title('Get a shared content element by ID')]
#[Description('Retrieves a single shared content element by its ID. Returns the full element data including type, name, language, content data, and the latest draft version as a JSON object.')]
class GetElement extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'element:view', $request->user() ) ) {
            throw new \Exception( 'Insufficient permissions' );
        }

        $v = $request->validate([
            'id' => 'required|string|max:36',
        ], [
            'id.required' => 'You must specify the ID of the element to retrieve.',
        ] );

        /** @var Element|null $element */
        $element = Element::withTrashed()->with( [
            'latest' => fn( $q ) => $q->select( 'id', 'versionable_id', 'data', 'lang', 'editor', 'published', 'publish_at', 'created_at' )
        ] )->find( $v['id'] );

        if( !$element ) {
            return Response::structured( ['error' => 'Element not found.'] );
        }

        $version = $element->latest;
        $vdata = $version?->data;
        $usedByPages = $element->bypages()->toBase()
            ->select( 'cms_pages.id', 'cms_pages.name', 'cms_pages.path' )
            ->cursor()->map( fn( $p ) => (array) $p )->all();

        $data = [
            'id' => $element->id,
            'type' => $element->type,
            'deleted' => $element->trashed(),
            'lang' => $version->lang ?? '',
            'editor' => $version->editor ?? '',
            'name' => $vdata->name ?? '',
            'data' => $vdata->data ?? new \stdClass(),
            'published' => $version->published ?? false,
            'publish_at' => $version->publish_at ?? null,
            'created_at' => $element->created_at?->format( 'Y-m-d H:i:s' ),
            'updated_at' => $version?->created_at?->format( 'Y-m-d H:i:s' ),
            'used_by_pages' => $usedByPages,
        ];

        return Response::structured( $data );
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
                ->description('The UUID of the element to retrieve.')
                ->required(),
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
        return Permission::can( 'element:view', $request->user() );
    }
}

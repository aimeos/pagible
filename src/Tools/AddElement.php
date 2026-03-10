<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Permission;
use Aimeos\Cms\Models\Element;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('add-element')]
#[Title('Create a reusable content element')]
#[Description('Creates a new shared content element (header, footer, CTA, etc.) that can be reused across multiple pages. Returns the created element as a JSON object.')]
class AddElement extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'element:add', $request->user() ) ) {
            throw new \Exception( 'Insufficient permissions' );
        }

        $validated = $request->validate([
            'type' => 'required|string|max:50',
            'name' => 'required|string|max:100',
            'lang' => 'string|max:5',
            'data' => 'required|array',
        ], [
            'type.required' => 'You must specify the element type, e.g., "heading", "text", "image", "contact". Use get-schemas to see available types.',
            'name.required' => 'You must specify a name for the element.',
            'data.required' => 'You must provide the element data as a JSON object with field values.',
        ] );

        return DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $validated, $request ) {

            $editor = (string) $request->user()?->name; // @phpstan-ignore-line property.notFound

            $versionId = Str::uuid7();

            $element = new Element();
            $element->latest_id = $versionId;
            $element->fill( [
                'type' => $validated['type'],
                'name' => $validated['name'],
                'lang' => $validated['lang'] ?? null,
                'data' => $validated['data'],
            ] );
            $element->tenant_id = \Aimeos\Cms\Tenancy::value();
            $element->editor = $editor;
            $element->save();

            $data = [
                'type' => $validated['type'],
                'name' => $validated['name'],
                'lang' => $validated['lang'] ?? null,
                'data' => $validated['data'],
            ];
            ksort( $data );

            $element->versions()->forceCreate( [
                'id' => $versionId,
                'data' => array_map( fn( $v ) => is_null( $v ) ? (string) $v : $v, $data ),
                'lang' => $validated['lang'] ?? null,
                'editor' => $editor,
            ] );

            return Response::structured( $element->refresh()->toArray() );
        }, 3 );
    }


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return [
            'type' => $schema->string()
                ->description('The element type, e.g., "heading", "text", "image", "contact", "cards". Use get-schemas to see all available types.')
                ->required(),
            'name' => $schema->string()
                ->description('A descriptive name for the element, e.g., "Main Footer CTA" or "Homepage Hero".')
                ->required(),
            'lang' => $schema->string()
                ->description('ISO language code, e.g., "en" or "de". Omit for language-independent elements.'),
            'data' => $schema->object()
                ->description('The element data as a JSON object. Fields depend on the type. For "text": {"text": "markdown content"}. For "heading": {"text": "Title", "level": "2"}. Use get-schemas to see field definitions.')
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
        return Permission::can( 'element:add', $request->user() );
    }
}

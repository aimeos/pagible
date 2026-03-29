<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Utils;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Validation;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\Version;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;


#[Name('add-element')]
#[Title('Create a reusable content element')]
#[Description('Creates a reusable content element. Requires type (use get-schemas), name (max 100 chars), and data (object with type-specific fields). Optional: lang (ISO code), files (array of file UUIDs). Returns the created element as JSON.')]
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

        $v = $request->validate([
            'type' => 'required|string|max:50',
            'name' => 'required|string|max:100',
            'lang' => 'nullable|string|max:5',
            'data' => 'required|array',
            'files' => 'array',
            'files.*' => 'string|max:36',
        ], [
            'type.required' => 'You must specify the element type, e.g., "heading", "text", "image", "contact". Use get-schemas to see available types.',
            'name.required' => 'You must specify a name for the element.',
            'data.required' => 'You must provide the element data as a JSON object with field values.',
        ] );

        Validation::element( $v['type'] );

        if( $v['type'] === 'html' && isset( $v['data']['text'] ) ) {
            $v['data']['text'] = Utils::html( (string) $v['data']['text'] );
        }

        return DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $v, $request ) {

            $editor = $request->user()?->email ?? request()->ip(); // @phpstan-ignore-line property.notFound
            $versionId = ( new Version )->newUniqueId();
            $files = $v['files'] ?? [];

            $element = new Element();
            $element->fill( [
                'type' => $v['type'],
                'name' => $v['name'],
                'lang' => $v['lang'] ?? null,
                'data' => $v['data'],
            ] );
            $element->tenant_id = \Aimeos\Cms\Tenancy::value();
            $element->latest_id = $versionId;
            $element->editor = $editor;
            $element->save();

            $element->files()->attach( $files );

            $data = [
                'type' => $v['type'],
                'name' => $v['name'],
                'lang' => $v['lang'] ?? null,
                'data' => $v['data'],
            ];
            ksort( $data );

            $version = $element->versions()->forceCreate( [
                'id' => $versionId,
                'data' => array_map( fn( $v ) => is_null( $v ) ? (string) $v : $v, $data ),
                'lang' => $v['lang'] ?? null,
                'editor' => $editor,
            ] );

            $version->files()->attach( $files );

            return Response::structured( $element->toArray() );
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
            'files' => $schema->array()
                ->items( $schema->string() )
                ->description( 'Array of file UUIDs to attach to the element.' ),
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

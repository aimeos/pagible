<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Utils;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\Version;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('save-element')]
#[Title('Save a shared content element')]
#[Description('Updates an existing element by ID. Only send fields you want to change — unsent fields are preserved. Returns the updated element as JSON.')]
class SaveElement extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'element:save', $request->user() ) ) {
            throw new \Exception( 'Insufficient permissions' );
        }

        $v = $request->validate([
            'id' => 'required|string|max:36',
            'name' => 'string|max:100',
            'lang' => 'nullable|string|max:5',
            'data' => 'array',
            'files' => 'array',
            'files.*' => 'string|max:36',
        ], [
            'id.required' => 'You must specify the ID of the element to save.',
        ] );

        /** @var Element|null $element */
        $element = Element::withTrashed()->with( 'latest' )->find( $v['id'] );

        if( !$element ) {
            return Response::structured( ['error' => 'Element not found.'] );
        }

        $type = $element->type ?? ( (array) ( $element->latest->data ?? [] ) )['type'] ?? '';

        if( $type === 'html' && isset( $v['data']['text'] ) ) {
            $v['data']['text'] = Utils::html( (string) $v['data']['text'] );
        }

        return DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $element, $v, $request ) {

            $editor = $request->user()?->email ?? request()->ip(); // @phpstan-ignore-line property.notFound
            $versionId = ( new Version )->newUniqueId();

            // Build input from latest version, then overlay changes
            $input = (array) ( $element->latest->data ?? [] );

            if( isset( $v['name'] ) ) {
                $input['name'] = $v['name'];
            }

            if( isset( $v['data'] ) ) {
                $input['data'] = $v['data'];
            }

            $version = $element->versions()->forceCreate( [
                'id' => $versionId,
                'data' => array_map( fn( $v ) => $v ?? '', $input ),
                'editor' => $editor,
                'lang' => $v['lang'] ?? $element->latest?->lang,
            ] );

            $version->files()->attach( (array) ( $v['files'] ?? [] ) );

            $element->forceFill( ['latest_id' => $versionId] )->save();
            $element->removeVersions();

            return Response::structured( [
                'id' => $element->id,
                'type' => $input['type'] ?? '',
                'name' => $input['name'] ?? '',
                'lang' => $v['lang'] ?? null,
                'data' => $input['data'] ?? new \stdClass(),
                'created_at' => (string) $element->created_at,
                'updated_at' => (string) $element->updated_at,
            ] );
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
            'id' => $schema->string()
                ->description( 'The UUID of the element to save. Use search-elements or list-elements to find the ID.' )
                ->required(),
            'name' => $schema->string()
                ->description( 'New name for the element.' ),
            'lang' => $schema->string()
                ->description( 'ISO language code for the version.' ),
            'data' => $schema->object()
                ->description( 'Element data as a JSON object. Fields depend on the element type. Use get-element to see the current type and get-schemas for available fields.' ),
            'files' => $schema->array()
                ->items( $schema->string() )
                ->description( 'Array of file UUIDs to attach to the version.' ),
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
        return Permission::can( 'element:save', $request->user() );
    }
}

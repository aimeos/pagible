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


#[Name('update-element')]
#[Title('Update a shared content element')]
#[Description('Updates an existing shared content element. Creates a new draft version. Returns the updated element as a JSON object.')]
class UpdateElement extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'element:save', $request->user() ) ) {
            throw new \Exception( 'Insufficient permissions' );
        }

        $validated = $request->validate([
            'id' => 'required|string|max:36',
            'name' => 'string|max:100',
            'lang' => 'string|max:5',
            'data' => 'array',
        ], [
            'id.required' => 'You must specify the ID of the element to update.',
        ] );

        /** @var Element|null $element */
        $element = Element::withTrashed()->find( $validated['id'] );

        if( !$element ) {
            return Response::structured( ['error' => 'Element not found.'] );
        }

        return DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $element, $validated, $request ) {

            $editor = (string) $request->user()?->name; // @phpstan-ignore-line property.notFound

            // Build input from latest version, then overlay changes
            $input = (array) ( $element->latest->data ?? [] );

            if( isset( $validated['name'] ) ) {
                $input['name'] = $validated['name'];
            }

            if( isset( $validated['data'] ) ) {
                $input['data'] = $validated['data'];
            }

            $versionId = Str::uuid7();

            $version = $element->versions()->forceCreate( [
                'id' => $versionId,
                'data' => array_map( fn( $v ) => $v ?? '', $input ),
                'editor' => $editor,
                'lang' => $validated['lang'] ?? $element->latest?->lang,
            ] );

            $element->forceFill( ['latest_id' => $versionId] )->save();
            $element->removeVersions();

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
            'id' => $schema->string()
                ->description('The UUID of the element to update. Use search-elements to find the ID.')
                ->required(),
            'name' => $schema->string()
                ->description('New name for the element.'),
            'lang' => $schema->string()
                ->description('ISO language code for the version.'),
            'data' => $schema->object()
                ->description('Updated element data as a JSON object. Fields depend on the element type.'),
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

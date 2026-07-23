<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Permission;
use Aimeos\Cms\Resource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('save-file')]
#[Title('Save file metadata')]
#[Description('Saves the name, description, or language of an existing media file. Creates a new draft version. Returns the updated file as a JSON object.')]
class SaveFile extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'file:save', $request->user() )
            || !Permission::can( 'file:view', $request->user() ) ) {
            throw new \Aimeos\Cms\Exception( 'Insufficient permissions' );
        }

        $v = $request->validate([
            'id' => 'required|string|max:36',
            'name' => 'string|max:255',
            'lang' => 'nullable|string|max:5',
            'description' => 'array',
            'latest_id' => 'required|string|max:36',
        ], [
            'id.required' => 'You must specify the ID of the file to save.',
            'latest_id.required' => 'You must pass the latest_id returned by get-file, add-file, or a previous save-file so concurrent edits are detected.',
        ] );

        $input = array_diff_key( $v, array_flip( ['id', 'latest_id'] ) );

        try {
            $file = Resource::saveFile( $v['id'], $input, $request->user(), $v['latest_id'] ?? null );
        } catch( ModelNotFoundException $e ) {
            return Response::structured( ['error' => 'File not found.'] );
        }

        $data = (array) ( $file->latest->data ?? [] );

        return Response::structured( [
            'id' => $file->id,
            'latest_id' => $file->latest_id,
            'name' => $data['name'] ?? $file->name,
            'mime' => $data['mime'] ?? $file->mime,
            'lang' => $data['lang'] ?? $file->lang,
            'path' => $data['path'] ?? $file->path,
            'previews' => $data['previews'] ?? $file->previews,
            'description' => $data['description'] ?? $file->description,
            'changed' => $file->changed,
            'created_at' => (string) $file->created_at,
            'updated_at' => (string) $file->updated_at,
        ] );
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
                ->description( 'The UUID of the file to save. Use search-files or list-files to find the ID.' )
                ->required(),
            'name' => $schema->string()
                ->description( 'New display name for the file.' ),
            'lang' => $schema->string()
                ->description( 'ISO language code for the file, e.g., "en" or "de".' ),
            'description' => $schema->object()
                ->description( 'Multilingual description object, e.g., {"en": "A sunset photo", "de": "Ein Sonnenuntergangsfoto"}. Used as alt text for images.' ),
            'latest_id' => $schema->string()
                ->description( 'Required. The latest_id value returned by get-file, add-file, or your previous save-file for this file. Ensures edits made by another editor in the meantime are merged instead of overwritten.' )
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
        return Permission::can( 'file:save', $request->user() )
            && Permission::can( 'file:view', $request->user() );
    }
}

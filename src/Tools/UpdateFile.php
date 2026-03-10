<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Permission;
use Aimeos\Cms\Models\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('update-file')]
#[Title('Update file metadata')]
#[Description('Updates the name, description, or language of an existing media file. Creates a new draft version. Returns the updated file as a JSON object.')]
class UpdateFile extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'file:save', $request->user() ) ) {
            throw new \Exception( 'Insufficient permissions' );
        }

        $validated = $request->validate([
            'id' => 'required|string|max:36',
            'name' => 'string|max:255',
            'lang' => 'string|max:5',
            'description' => 'array',
        ], [
            'id.required' => 'You must specify the ID of the file to update.',
        ] );

        /** @var File|null $file */
        $file = File::withTrashed()->find( $validated['id'] );

        if( !$file ) {
            return Response::structured( ['error' => 'File not found.'] );
        }

        return DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $file, $validated, $request ) {

            $editor = (string) $request->user()?->name; // @phpstan-ignore-line property.notFound

            // Clone file to build version data without saving to the model directly
            $clone = clone $file;
            $latestData = (array) ( $file->latest->data ?? [] );
            $clone->fill( array_replace( $latestData, array_intersect_key( $validated, array_flip( ['name', 'lang'] ) ) ) );

            if( isset( $validated['description'] ) ) {
                $clone->description = $validated['description'];
            }

            $clone->previews = $latestData['previews'] ?? $file->previews;
            $clone->path = $latestData['path'] ?? $file->path;
            $clone->editor = $editor;

            $versionId = Str::uuid7();

            $version = $clone->versions()->forceCreate( [
                'id' => $versionId,
                'lang' => $validated['lang'] ?? $file->latest->lang ?? $file->lang,
                'editor' => $editor,
                'data' => $clone->toArray(),
            ] );

            $file->forceFill( ['latest_id' => $versionId] )->save();
            $file->removeVersions();

            return Response::structured( [
                'id' => $file->id,
                'name' => $clone->name,
                'mime' => $clone->mime,
                'lang' => $clone->lang,
                'path' => $clone->path,
                'previews' => $clone->previews,
                'description' => $clone->description,
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
                ->description('The UUID of the file to update. Use list-files or search-files to find the ID.')
                ->required(),
            'name' => $schema->string()
                ->description('New display name for the file.'),
            'lang' => $schema->string()
                ->description('ISO language code for the file, e.g., "en" or "de".'),
            'description' => $schema->object()
                ->description('Multilingual description object, e.g., {"en": "A sunset photo", "de": "Ein Sonnenuntergangsfoto"}. Used as alt text for images.'),
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
        return Permission::can( 'file:save', $request->user() );
    }
}

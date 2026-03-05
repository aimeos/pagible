<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Permission;
use Aimeos\Cms\Models\File;
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
#[Name('get-file')]
#[Title('Get file details by ID')]
#[Description('Retrieves full details for a media file including name, MIME type, path, preview URLs, descriptions, and transcription data. Returns the file as a JSON object.')]
class GetFile extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'file:view', $request->user() ) ) {
            throw new \Exception( 'Insufficient permissions' );
        }

        $validated = $request->validate([
            'id' => 'required|string|max:36',
        ], [
            'id.required' => 'You must specify the file ID.',
        ] );

        /** @var File|null $file */
        $file = File::withTrashed()->find( $validated['id'] );

        if( !$file ) {
            return Response::structured( ['error' => 'File not found.'] );
        }

        $data = [
            'id' => $file->id,
            'name' => $file->name,
            'mime' => $file->mime,
            'lang' => $file->lang,
            'path' => $file->path,
            'previews' => $file->previews,
            'description' => $file->description,
            'transcription' => $file->transcription,
            'deleted' => $file->trashed(),
            'created_at' => $file->created_at?->format( 'Y-m-d H:i:s' ),
            'updated_at' => $file->updated_at?->format( 'Y-m-d H:i:s' ),
        ];

        // Include pages and elements that reference this file
        /** @phpstan-ignore argument.type */
        $data['used_by_pages'] = $file->bypages->map( fn( \Aimeos\Cms\Models\Page $p ) => [
            'id' => $p->id,
            'name' => $p->name,
            'path' => $p->path,
        ] )->all();

        $data['used_by_elements'] = $file->byelements->map( fn( \Aimeos\Cms\Models\Element $e ) => [
            'id' => $e->id,
            'type' => $e->type,
            'name' => $e->name,
        ] )->all();

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
                ->description('The UUID of the file to retrieve.')
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
        return Permission::can( 'file:view', $request->user() );
    }
}

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

        $v = $request->validate([
            'id' => 'required|string|max:36',
        ], [
            'id.required' => 'You must specify the file ID.',
        ] );

        /** @var File|null $file */
        $file = File::withTrashed()->with( [
            'latest' => fn( $q ) => $q->select( 'id', 'versionable_id', 'data', 'lang', 'editor', 'published', 'publish_at', 'created_at' )
        ] )->find( $v['id'] );

        if( !$file ) {
            return Response::structured( ['error' => 'File not found.'] );
        }

        $version = $file->latest;
        $vdata = $version?->data;
        $usedByElements = $file->byelements()->toBase()
            ->select( 'cms_elements.id', 'cms_elements.type', 'cms_elements.name' )
            ->cursor()->map( fn( $e ) => (array) $e )->all();
        $usedByPages = $file->bypages()->toBase()
            ->select( 'cms_pages.id', 'cms_pages.name', 'cms_pages.path' )
            ->cursor()->map( fn( $p ) => (array) $p )->all();

        $data = [
            'id' => $file->id,
            'deleted' => $file->trashed(),
            'lang' => $version->lang ?? '',
            'editor' => $version->editor ?? '',
            'name' => $vdata->name ?? '',
            'mime' => $vdata->mime ?? '',
            'path' => $vdata->path ?? '',
            'previews' => $vdata->previews ?? [],
            'description' => $vdata->description ?? new \stdClass(),
            'transcription' => $vdata->transcription ?? new \stdClass(),
            'published' => $version->published ?? false,
            'publish_at' => $version->publish_at ?? null,
            'created_at' => $file->created_at?->format( 'Y-m-d H:i:s' ),
            'updated_at' => $version?->created_at?->format( 'Y-m-d H:i:s' ),
            'used_by_elements' => $usedByElements,
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

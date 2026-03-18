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
#[Name('list-files')]
#[Title('Browse the media library')]
#[Description('Lists files from the media library with optional filters by MIME type, name, or language. Returns up to 25 files as a JSON array with id, name, mime, path, previews, and description.')]
class ListFiles extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'file:view', $request->user() ) ) {
            throw new \Exception( 'Insufficient permissions' );
        }

        $query = File::withTrashed()->orderBy( 'updated_at', 'desc' );

        if( $mime = $request->get( 'mime' ) ) {
            $query->where( 'mime', 'like', $mime . '%' );
        }

        if( $term = $request->get( 'term' ) ) {
            $query->where( 'name', 'like', '%' . $term . '%' );
        }

        if( $lang = $request->get( 'lang' ) ) {
            $query->where( 'lang', $lang );
        }

        $result = $query->take( 25 )->get()->map( function( $item ) {
            /** @var File $item */
            return [
                'id' => $item->id,
                'name' => $item->name,
                'mime' => $item->mime,
                'lang' => $item->lang,
                'path' => $item->path,
                'previews' => $item->previews,
                'description' => $item->description,
                'deleted' => $item->trashed(),
                'created_at' => $item->created_at?->format( 'Y-m-d H:i:s' ),
            ];
        } );

        /** @phpstan-ignore argument.type */
        return Response::structured( ['files' => $result->all()] );
    }


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return [
            'mime' => $schema->string()
                ->description('Filter by MIME type prefix, e.g., "image" for all images, "video" for all videos, "image/png" for PNG files only.'),
            'term' => $schema->string()
                ->description('Search keyword to match against file names.'),
            'lang' => $schema->string()
                ->description('Filter by ISO language code, e.g., "en" or "de".'),
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

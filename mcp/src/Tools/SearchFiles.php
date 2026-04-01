<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Filter;
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
#[Name('search-files')]
#[Title('Search files by keyword')]
#[Description('Searches the media library for files matching a keyword in the name, description, or transcription. Returns up to 25 matching files as a JSON array.')]
class SearchFiles extends Tool
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
            'term' => 'string|max:255',
            'mime' => 'string|max:50',
            'lang' => 'nullable|string|max:5',
            'trashed' => 'string|in:without,with,only',
            'publish' => 'string|in:PUBLISHED,DRAFT,SCHEDULED',
            'editor' => 'string|max:255',
        ] );

        $search = File::search( mb_substr( trim( (string) ( $v['term'] ?? '' ) ), 0, 200 ) )
            ->query( fn( $q ) => $q->with( 'latest' ) )
            ->searchFields( 'draft' )
            ->take( 25 );

        $result = Filter::files( $search, $v )->get()->map( function( $item ) {
            /** @var File $item */
            $data = $item->latest?->data ?? new \stdClass();
            return [
                'id' => $item->id,
                'name' => $data->name ?? null,
                'mime' => $data->mime ?? null,
                'lang' => $item->latest?->lang,
                'path' => $data->path ?? null,
                'previews' => $data->previews ?? null,
                'description' => $data->description ?? null,
                'transcription' => $data->transcription ?? null,
                'editor' => $item->latest?->editor,
                'deleted' => $item->trashed(),
                'created_at' => $item->created_at?->format( 'Y-m-d H:i:s' ),
                'updated_at' => $item->updated_at?->format( 'Y-m-d H:i:s' ),
            ];
        } );

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
            'term' => $schema->string()
                ->description('Search keyword to match against file name, description, and transcription.'),
            'mime' => $schema->string()
                ->description('Filter by MIME type prefix, e.g., "image" for all images, "video" for all videos.'),
            'lang' => $schema->string()
                ->description('Filter by ISO language code, e.g., "en" or "de".'),
            'trashed' => $schema->string()
                ->description('Include trashed items: "without" (default), "with" (include deleted), or "only" (only deleted).'),
            'publish' => $schema->string()
                ->description('Filter by publish status: "PUBLISHED", "DRAFT", or "SCHEDULED".'),
            'editor' => $schema->string()
                ->description('Filter by editor name.'),
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

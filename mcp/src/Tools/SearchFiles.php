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
#[Description('Lists and searches media files. Optional: term (full-text search), mime, lang, trashed, publish, editor. Without term, returns all matching files. Returns up to 25 results.')]
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
            ->query( fn( $q ) => $q->select( 'cms_files.id', 'cms_files.created_at', 'cms_files.updated_at', 'cms_files.deleted_at', 'cms_files.latest_id' )
            ->with( ['latest' => fn( $q ) => $q->select( 'id', 'versionable_id', 'data', 'lang', 'editor' )] ) )
            ->searchFields( 'draft' )
            ->take( 25 );

        $result = [];

        foreach( Filter::files( $search, $v )->get() as $item )
        {
            /** @var File $item */
            $latest = $item->latest;
            $data = $latest->data ?? new \stdClass();
            $result[] = [
                'id' => $item->id,
                'name' => $data->name ?? null,
                'mime' => $data->mime ?? null,
                'path' => $data->path ?? null,
                'lang' => $latest?->lang,
                'editor' => $latest?->editor,
                'deleted' => $item->trashed(),
                'created_at' => $item->created_at?->format( 'Y-m-d H:i:s' ),
                'updated_at' => $item->updated_at?->format( 'Y-m-d H:i:s' ),
            ];
        }

        return Response::structured( ['files' => $result] );
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

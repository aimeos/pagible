<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Filter;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Version;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[IsReadOnly]
#[Name('search-files')]
#[Title('Search files by keyword')]
#[Description('Lists and searches media files. Optional: term (full-text search), mime, lang, trashed, publish, editor. Without term, returns all matching files. Returns up to 25 results.')]
class SearchFiles extends Tool
{
    protected const PERMISSIONS = ['file:view'];


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : \Laravel\Mcp\ResponseFactory
    {
        $v = $request->validate([
            'term' => 'string|max:255',
            'mime' => 'string|max:50',
            'lang' => 'nullable|string|max:5',
            'trashed' => 'string|in:without,with,only',
            'publish' => 'string|in:PUBLISHED,DRAFT,SCHEDULED',
            'editor' => 'string|max:255',
        ] );

        $search = Filter::search( File::class, $v['term'] ?? '' )
            ->query( fn( $q ) => $q->select( 'cms_files.id', 'cms_files.tenant_id', 'cms_files.created_at', 'cms_files.updated_at', 'cms_files.deleted_at', 'cms_files.latest_id' )
            ->with( ['latest' => fn( $q ) => $q->select( Version::SELECT_COLUMNS )] ) )
            ->take( 25 );

        $result = [];

        foreach( Filter::files( $search, $v )->get() as $item )
        {
            /** @var File $item */
            $result[] = Presenter::file( $item, false );
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
}

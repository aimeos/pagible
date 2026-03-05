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

        $validated = $request->validate([
            'term' => 'required|string|max:255',
            'mime' => 'string|max:50',
            'lang' => 'string|max:5',
        ], [
            'term.required' => 'You must specify a search term. For example, "logo" or "hero".',
        ] );

        $term = $validated['term'];

        $query = File::withTrashed()
            ->where( function( $builder ) use ( $term ) {
                $builder->where( 'name', 'like', '%' . $term . '%' )
                    ->orWhere( 'description', 'like', '%' . $term . '%' )
                    ->orWhere( 'transcription', 'like', '%' . $term . '%' );
            } )
            ->orderBy( 'updated_at', 'desc' );

        if( !empty( $validated['mime'] ) ) {
            $query->where( 'mime', 'like', $validated['mime'] . '%' );
        }

        if( !empty( $validated['lang'] ) ) {
            $query->where( 'lang', $validated['lang'] );
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
        return Response::structured( $result->all() );
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
                ->description('Search keyword to match against file name, description, and transcription.')
                ->required(),
            'mime' => $schema->string()
                ->description('Filter by MIME type prefix, e.g., "image" for all images, "video" for all videos.'),
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

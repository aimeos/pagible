<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Utils;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Validation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('save-page')]
#[Title('Save an existing page')]
#[Description('Updates an existing page by ID. Only send fields you want to change — unsent fields are preserved from the latest version. Content, meta, and config are fully replaced when provided. Use get-schemas for content types and meta/config field definitions. Returns the updated page as JSON.')]
class SavePage extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'page:save', $request->user() ) ) {
            throw new \Exception( 'Insufficient permissions' );
        }

        $v = $request->validate([
            'id' => 'required|string|max:36',
            'name' => 'string|max:50',
            'title' => 'string|max:100',
            'lang' => 'string|max:5',
            'content' => 'array',
            'content.*.id' => 'string|max:10',
            'content.*.type' => 'required|string|max:50',
            'content.*.group' => 'string|max:50',
            'content.*.data' => 'required|array',
            'meta' => 'array',
            'config' => 'array',
            'to' => 'string|max:2048',
            'tag' => 'string|max:50',
            'theme' => 'string|max:50',
            'type' => 'string|max:50',
            'domain' => 'string|max:255',
            'path' => 'string|max:255',
            'status' => 'integer|in:0,1,2',
            'cache' => 'integer|min:0',
            'related_id' => 'string|max:36',
            'files' => 'array',
            'files.*' => 'string|max:36',
            'elements' => 'array',
            'elements.*' => 'string|max:36',
            'latestId' => 'string|max:36',
        ], [
            'id.required' => 'You must specify the ID of the page to save.',
        ] );

        if( isset( $v['content'] ) ) {
            $v['content'] = Validation::content( $v['content'] );
        }

        if( isset( $v['title'] ) && !isset( $v['path'] ) ) {
            $v['path'] = Utils::slugify( $v['title'] );
        }

        if( isset( $v['meta'] ) ) {
            $v['meta'] = Validation::structured( $v['meta'], 'meta', new \stdClass() );
        }

        if( isset( $v['config'] ) ) {
            $v['config'] = Validation::structured( $v['config'], 'config', new \stdClass() );
        }

        $input = array_diff_key( $v, array_flip( ['id', 'files', 'elements', 'latestId'] ) );

        try {
            $page = Resource::savePage(
                $v['id'], $input, $request->user(),
                $v['files'] ?? null, $v['elements'] ?? null,
                $v['latestId'] ?? null,
            );
        } catch( ModelNotFoundException $e ) {
            return Response::structured( ['error' => 'Page not found.'] );
        }

        $data = (array) ( $page->latest->data ?? [] );
        $aux = (array) ( $page->latest->aux ?? [] );

        return Response::structured( array_merge( $data, [
            'id' => $page->id,
            'meta' => $aux['meta'] ?? new \stdClass(),
            'config' => $aux['config'] ?? new \stdClass(),
            'content' => $aux['content'] ?? [],
            'status' => $page->status,
            'cache' => $page->cache,
            'changed' => $page->changed,
            'created_at' => (string) $page->created_at,
            'updated_at' => (string) $page->updated_at,
            'url' => route( 'cms.page', ['path' => $data['path'] ?? ''] ),
        ] ) );
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
                ->description( 'The UUID of the page to save. Use search-pages or list-pages to find the ID.' )
                ->required(),
            'name' => $schema->string()
                ->description( 'New short name for the page (max 50 characters).' ),
            'title' => $schema->string()
                ->description( 'New page title (max 100 characters). Also updates the URL path slug unless path is explicitly set.' ),
            'lang' => $schema->string()
                ->description( 'ISO language code for the version, e.g., "en" or "de".' ),
            'content' => $schema->array()
                ->items( $schema->object( [
                    'id' => $schema->string()
                        ->description( 'Existing element ID to preserve. Omit to auto-generate a new ID.' ),
                    'type' => $schema->string()
                        ->description( 'Content element type. Use get-schemas for available types.' )
                        ->required(),
                    'group' => $schema->string()
                        ->description( 'Layout section, e.g., "main", "footer". Use "main" if unsure.' ),
                    'data' => $schema->object()
                        ->description( 'Field values for this element. Use get-schemas for available fields per type.' )
                        ->required(),
                ] ) )
                ->description( 'Content elements. Replaces all existing content. Use get-schemas for available types and fields.' ),
            'meta' => $schema->object()
                ->description( 'Meta data object keyed by type. Each value is an object with the data fields. Use get-schemas for available types and fields.' ),
            'config' => $schema->object()
                ->description( 'Page configuration keyed by type. Each value is an object with the data fields. Use get-schemas for available types and fields.' ),
            'to' => $schema->string()
                ->description( 'Redirect URL. If set, the page redirects to this URL instead of rendering content.' ),
            'tag' => $schema->string()
                ->description( 'Tag name to identify a page, e.g., for the starting point of a navigation structure.' ),
            'theme' => $schema->string()
                ->description( 'Theme name assigned to the page.' ),
            'type' => $schema->string()
                ->description( 'Page type for using different theme templates.' ),
            'domain' => $schema->string()
                ->description( 'Domain name the page is assigned to.' ),
            'path' => $schema->string()
                ->description( 'Unique URL segment. Auto-generated from title if not set explicitly.' ),
            'status' => $schema->integer()
                ->description( 'Visibility: 0=inactive, 1=visible, 2=hidden in navigation.' ),
            'cache' => $schema->integer()
                ->description( 'Cache lifetime in minutes.' ),
            'related_id' => $schema->string()
                ->description( 'Translation ID linking pages with the same content in different languages.' ),
            'files' => $schema->array()
                ->items( $schema->string() )
                ->description( 'Array of file UUIDs to attach to the version.' ),
            'elements' => $schema->array()
                ->items( $schema->string() )
                ->description( 'Array of shared element UUIDs to attach to the version.' ),
            'latestId' => $schema->string()
                ->description( 'Version ID the caller last retrieved. Enables conflict detection and three-way merge when another editor has saved in the meantime.' ),
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
        return Permission::can( 'page:save', $request->user() );
    }
}

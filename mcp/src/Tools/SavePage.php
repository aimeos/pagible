<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Utils;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\Version;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
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
    use Concerns\SanitizesPages;


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
        ], [
            'id.required' => 'You must specify the ID of the page to save.',
        ] );

        /** @var Page|null $page */
        $page = Page::withTrashed()->with( 'latest' )->find( $v['id'] );

        if( !$page ) {
            return Response::structured( ['error' => 'Page not found.'] );
        }

        $v = $this->sanitize( $v, $request->user() );

        return DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $page, $v, $request ) {

            $editor = $request->user()?->email ?? request()->ip(); // @phpstan-ignore-line property.notFound
            $versionId = ( new Version )->newUniqueId();

            $input = array_diff_key( $v, array_flip( ['id', 'meta', 'config', 'content', 'files', 'elements'] ) );

            if( isset( $v['title'] ) && !isset( $v['path'] ) ) {
                $input['path'] = Utils::slugify( $v['title'] );
            }

            array_walk( $input, fn( &$v, $k ) => $v = !in_array( $k, ['related_id'] ) ? ( $v ?? '' ) : $v );
            $data = array_replace( (array) ( $page->latest->data ?? [] ), $input );

            $aux = (array) ( $page->latest->aux ?? [] );

            if( isset( $v['content'] ) ) {
                $aux['content'] = $v['content'];
            }

            if( isset( $v['meta'] ) ) {
                $aux['meta'] = $this->buildStructured( $v['meta'], 'meta', new \stdClass() );
            }

            if( isset( $v['config'] ) ) {
                $aux['config'] = $this->buildStructured( $v['config'], 'config', new \stdClass() );
            }

            $version = $page->versions()->forceCreate([
                'id' => $versionId,
                'data' => $data,
                'editor' => $editor,
                'lang' => $v['lang'] ?? $page->latest?->lang,
                'aux' => $aux,
            ] );

            $version->elements()->attach( $v['elements'] ?? [] );
            $version->files()->attach( $v['files'] ?? [] );

            $page->forceFill( ['latest_id' => $versionId] )->save();
            $page->removeVersions();

            return Response::structured( array_merge( $data, [
                'id' => $page->id,
                'meta' => $aux['meta'] ?? new \stdClass(),
                'config' => $aux['config'] ?? new \stdClass(),
                'content' => $aux['content'] ?? [],
                'status' => $page->status,
                'cache' => $page->cache,
                'created_at' => (string) $page->created_at,
                'updated_at' => (string) $page->updated_at,
                'url' => route( 'cms.page', ['path' => $data['path'] ?? ''] ),
            ] ) );
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

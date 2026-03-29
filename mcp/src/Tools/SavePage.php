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
#[Description('Saves the content, meta data, config, title, or name of an existing page. Creates a new draft version. Returns the updated page as a JSON object.')]
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

        $validated = $request->validate([
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
        ], [
            'id.required' => 'You must specify the ID of the page to save.',
        ] );

        /** @var Page|null $page */
        $page = Page::withTrashed()->with( 'latest' )->find( $validated['id'] );

        if( !$page ) {
            return Response::structured( ['error' => 'Page not found.'] );
        }

        return DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $page, $validated, $request ) {

            $editor = $request->user()?->email ?? request()->ip(); // @phpstan-ignore-line property.notFound
            $versionId = ( new Version )->newUniqueId();

            // Build data from latest version, then overlay changes
            $changes = array_intersect_key( $validated, array_flip( ['name', 'title'] ) );

            if( isset( $validated['title'] ) ) {
                $changes['path'] = Utils::slugify( $validated['title'] );
            }

            $data = array_replace( (array) ( $page->latest->data ?? [] ), $changes );
            $aux = (array) ( $page->latest->aux ?? [] );

            if( isset( $validated['content'] ) ) {
                $aux['content'] = $this->buildContent( $validated['content'] );
            }

            if( isset( $validated['meta'] ) ) {
                $aux['meta'] = $this->buildStructured( $validated['meta'], 'meta', $aux['meta'] ?? new \stdClass() );
            }

            if( isset( $validated['config'] ) ) {
                $aux['config'] = $this->buildStructured( $validated['config'], 'config', $aux['config'] ?? new \stdClass() );
            }

            $version = $page->versions()->forceCreate([
                'id' => $versionId,
                'data' => array_map( fn( $v ) => $v ?? '', $data ),
                'editor' => $editor,
                'lang' => $validated['lang'] ?? $page->latest?->lang,
                'aux' => $aux,
            ] );

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
                ->description( 'New page title (max 100 characters). Also updates the URL path slug.' ),
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


    /**
     * Builds content elements from the validated input.
     *
     * @param array<int, array<string, mixed>> $items Content element items
     * @return array<int, object> Structured content elements
     */
    private function buildContent( array $items ) : array
    {
        $schemas = config( 'cms.schemas.content', [] );

        return array_values( array_map( function( $item ) use ( $schemas ) {
            $type = $item['type'];
            $group = $item['group'] ?? $schemas[$type]['group'] ?? 'main';

            return (object) [
                'id' => $item['id'] ?? Utils::uid(),
                'type' => $type,
                'group' => $group,
                'data' => (object) $item['data'],
            ];
        }, $items ) );
    }


    /**
     * Builds structured meta or config objects from the validated input.
     *
     * @param array<string, array<string, mixed>> $items Keyed by type name, values are data fields
     * @param string $section Schema section ('meta' or 'config')
     * @param array<string, mixed>|object $existing Existing meta/config data
     * @return object Structured meta/config object
     */
    private function buildStructured( array $items, string $section, array|object $existing ) : object
    {
        $schemas = config( "cms.schemas.{$section}", [] );
        $result = (object) ( (array) $existing );

        foreach( $items as $type => $data )
        {
            $group = $schemas[$type]['group'] ?? 'basic';
            $existingId = $result->{$type}->id ?? null;

            $result->{$type} = (object) [
                'id' => $existingId ?? Utils::uid(),
                'type' => $type,
                'group' => $group,
                'data' => (object) $data,
            ];
        }

        return $result;
    }
}

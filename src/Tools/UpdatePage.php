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


#[Name('update-page')]
#[Title('Update an existing page')]
#[Description('Updates the content, title, name, or meta description of an existing page. Creates a new draft version. Returns the updated page as a JSON object.')]
class UpdatePage extends Tool
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
            'summary' => 'string|max:200',
            'content' => 'string',
            'lang' => 'string|max:5',
        ], [
            'id.required' => 'You must specify the ID of the page to update.',
        ] );

        /** @var Page|null $page */
        $page = Page::withTrashed()->with( 'latest' )->find( $validated['id'] );

        if( !$page ) {
            return Response::structured( ['error' => 'Page not found.'] );
        }

        return DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $page, $validated, $request ) {

            $editor = (string) $request->user()?->name; // @phpstan-ignore-line property.notFound
            $versionId = ( new Version )->newUniqueId();

            // Build data from latest version, then overlay changes
            $data = (array) ( $page->latest->data ?? [] );
            $aux = (array) ( $page->latest->aux ?? [] );

            if( isset( $validated['name'] ) ) {
                $data['name'] = $validated['name'];
            }

            if( isset( $validated['title'] ) ) {
                $data['title'] = $validated['title'];
                $data['path'] = Utils::slugify( $validated['title'] );
            }

            if( isset( $validated['content'] ) )
            {
                $content = $aux['content'] ?? [];

                // Find first text element or create one
                $found = false;
                foreach( $content as &$el )
                {
                    if( ( $el['type'] ?? $el->type ?? '' ) === 'text' )
                    {
                        if( is_object( $el ) ) {
                            $el->data->text = $validated['content']; // @phpstan-ignore property.notFound
                        } else {
                            $el['data']['text'] = $validated['content'];
                        }

                        $found = true;
                        break;
                    }
                }

                if( !$found )
                {
                    $content[] = [
                        'id' => Utils::uid(),
                        'type' => 'text',
                        'group' => 'main',
                        'data' => [
                            'text' => $validated['content'],
                        ]
                    ];
                }

                $aux['content'] = $content;
            }

            if( isset( $validated['summary'] ) )
            {
                $meta = $aux['meta'] ?? [];

                if( is_object( $meta ) ) {
                    $meta = (array) $meta;
                }

                $meta['meta-tags'] = [
                    'id' => $meta['meta-tags']['id'] ?? $meta['meta-tags']->id ?? Utils::uid(),
                    'type' => 'meta-tags',
                    'group' => 'basic',
                    'data' => [
                        'description' => $validated['summary'],
                    ]
                ];

                $aux['meta'] = $meta;
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

            return Response::structured( [
                'id' => $page->id,
                'tag' => $data['tag'] ?? '',
                'lang' => $data['lang'] ?? '',
                'path' => $data['path'] ?? '',
                'domain' => $data['domain'] ?? '',
                'to' => $data['to'] ?? '',
                'name' => $data['name'] ?? '',
                'title' => $data['title'] ?? '',
                'type' => $data['type'] ?? '',
                'theme' => $data['theme'] ?? '',
                'meta' => $aux['meta'] ?? new \stdClass(),
                'config' => $aux['config'] ?? new \stdClass(),
                'content' => $aux['content'] ?? [],
                'status' => $page->status,
                'cache' => $page->cache,
                'created_at' => (string) $page->created_at,
                'updated_at' => (string) $page->updated_at,
                'url' => route( 'cms.page', ['path' => $data['path'] ?? ''] ),
            ] );
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
                ->description('The UUID of the page to update. Use search-pages or list-pages to find the ID.')
                ->required(),
            'name' => $schema->string()
                ->description('New short name for the page (max 50 characters).'),
            'title' => $schema->string()
                ->description('New page title (max 100 characters). Also updates the URL path slug.'),
            'summary' => $schema->string()
                ->description('New meta description for the page (max 200 characters, plaintext).'),
            'content' => $schema->string()
                ->description('New page content in markdown format. Replaces the first text element.'),
            'lang' => $schema->string()
                ->description('ISO language code for the version, e.g., "en" or "de".'),
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

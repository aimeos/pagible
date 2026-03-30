<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Utils;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Validation;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\Version;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('add-page')]
#[Title('Create a new page within the page tree')]
#[Description('Creates a new page in the page tree. Requires lang (ISO code like "en"), name (max 50 chars), title (max 100 chars), content (array of {type, data} objects — use get-schemas for types), and meta with meta-tags description for SEO. Optional: config, to, tag, theme, type, domain, path, status (0/1/2), cache (minutes), related_id, parent_id, ref, files, elements. Returns the created page as JSON.')]
class AddPage extends Tool
{
    private int $numcalls = 0;


    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'page:add', $request->user() ) ) {
            throw new \Exception( 'Insufficient permissions' );
        }

        if( $this->numcalls > 0 ) {
            return Response::structured( ['error' => 'Only one page can be created at a time.'] );
        }

        $v = $request->validate([
            'lang' => 'required|string|max:5',
            'name' => 'required|string|max:50',
            'title' => 'required|string|max:100',
            'content' => 'required|array',
            'content.*.type' => 'required|string|max:50',
            'content.*.group' => 'string|max:50',
            'content.*.data' => 'required|array',
            'meta' => 'required|array',
            'meta.meta-tags' => 'required|array',
            'meta.meta-tags.description' => 'required|string|max:300',
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
            'parent_id' => 'string|max:36',
            'ref' => 'string|max:36',
            'files' => 'array',
            'files.*' => 'string|max:36',
            'elements' => 'array',
            'elements.*' => 'string|max:36',
        ], [
            'lang.required' => 'You must specify a language code from the list of available locales. For example, "en" or "en-US".',
            'name.required' => 'You must specify a name for the page and it must not be longer than 50 characters.',
            'title.required' => 'You must specify a page title and it must not be longer than 100 characters.',
            'content.required' => 'You must provide content elements for the page. Use get-schemas for available types.',
            'meta.required' => 'You must provide meta data with at least a meta-tags description for SEO.',
            'meta.meta-tags.required' => 'You must provide meta-tags with a description for SEO.',
            'meta.meta-tags.description.required' => 'You must provide a meta description in meta.meta-tags.description for SEO. It should be 150-160 characters.',
        ] );

        $v = Validation::page( $v, $request->user() );

        if( isset( $v['content'] ) ) {
            $v['content'] = Validation::content( $v['content'] );
        }

        $page = new Page();
        $pid = $v['parent_id'] ?? null;
        $rid = $v['ref'] ?? null;

        /** @var Page|null $parent */
        $parent = $pid ? Page::withTrashed()->find( $pid ) : null;
        $editor = Utils::editor( $request->user() );
        $versionId = ( new Version )->newUniqueId();

        $meta = isset( $v['meta'] )
            ? Validation::structured( $v['meta'], 'meta', new \stdClass() )
            : new \stdClass();

        $config = isset( $v['config'] )
            ? Validation::structured( $v['config'], 'config', new \stdClass() )
            : new \stdClass();

        $content = $v['content'] ?? [];

        $input = array_diff_key( $v, array_flip( ['content', 'config', 'meta', 'files', 'elements'] ) );
        $input['path'] = $v['path'] ?? Utils::slugify( $v['title'] );
        $input['to'] = $v['to'] ?? '';
        $input['tag'] = $v['tag'] ?? '';
        $input['theme'] = $v['theme'] ?? $parent?->latest?->data->theme ?? '';
        $input['type'] = $v['type'] ?? '';
        $input['domain'] = $v['domain'] ?? $parent?->latest?->data->domain ?? '';
        $input['status'] = $v['status'] ?? 0;
        $input['cache'] = $v['cache'] ?? 5;
        $input['related_id'] = $v['related_id'] ?? null;

        $page->tenant_id = \Aimeos\Cms\Tenancy::value();
        $page->editor = $editor;
        $page->fill( $input + [
            'meta' => $meta,
            'content' => $content,
            'latest_id' => $versionId,
        ] );

        $vdata = [
            'id' => $versionId,
            'lang' => $v['lang'],
            'editor' => $editor,
            'data' => array_map( fn( $v ) => is_null( $v ) ? (string) $v : $v, $input ),
            'aux' => [
                'meta' => $meta,
                'config' => $config,
                'content' => $content,
            ]
        ];

        Utils::lockedTransaction( function() use ( $rid, $pid, $page, $v, $vdata ) {

            $files = $v['files'] ?? [];
            $elements = $v['elements'] ?? [];

            Resource::position( $page, $rid, $pid );

            $page->save();

            $page->files()->attach( $files );
            $page->elements()->attach( $elements );

            $version = $page->versions()->forceCreate( $vdata );

            $version->files()->attach( $files );
            $version->elements()->attach( $elements );

        } );

        $this->numcalls++;
        return Response::structured( $page->toArray() );
    }


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return [
            'lang' => $schema->string()
                ->description('ISO language code from the get-locales tool call, e.g., "en" or "en-US".')
                ->required(),
            'name' => $schema->string()
                ->description('Short name of the page for menus in the language of the page. Should not be longer than 30 characters.')
                ->required(),
            'title' => $schema->string()
                ->description('Engaging and SEO optimized page title in the language of the page. Must be unique for each page and not longer than 60 characters.')
                ->required(),
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
                ->description( 'Content elements. Use get-schemas for available types and fields.' )
                ->required(),
            'meta' => $schema->object()
                ->description( 'Meta data object keyed by type. Must include "meta-tags" with a "description" field (150-160 chars) for SEO. Use get-schemas for available types and fields.' )
                ->required(),
            'config' => $schema->object()
                ->description( 'Page configuration keyed by type. Each value is an object with the data fields. Use get-schemas for available types and fields.' ),
            'to' => $schema->string()
                ->description( 'Redirect URL. If set, the page redirects to this URL instead of rendering content.' ),
            'tag' => $schema->string()
                ->description( 'Tag name to identify a page, e.g., for the starting point of a navigation structure.' ),
            'theme' => $schema->string()
                ->description( 'Theme name assigned to the page. Inherited from parent if omitted.' ),
            'type' => $schema->string()
                ->description( 'Page type for using different theme templates.' ),
            'domain' => $schema->string()
                ->description( 'Domain name the page is assigned to. Inherited from parent if omitted.' ),
            'path' => $schema->string()
                ->description( 'Unique URL segment. Auto-generated from title if omitted.' ),
            'status' => $schema->integer()
                ->description( 'Visibility: 0=inactive (default), 1=visible, 2=hidden in navigation.' ),
            'cache' => $schema->integer()
                ->description( 'Cache lifetime in minutes. Default: 5.' ),
            'related_id' => $schema->string()
                ->description( 'Translation ID linking pages with the same content in different languages.' ),
            'parent_id' => $schema->string()
                ->description( 'ID of the parent page where the new page will be added below.' ),
            'ref' => $schema->string()
                ->description( 'ID of a sibling page to insert before. Takes priority over parent_id positioning.' ),
            'files' => $schema->array()
                ->items( $schema->string() )
                ->description( 'Array of file UUIDs to attach to the page.' ),
            'elements' => $schema->array()
                ->items( $schema->string() )
                ->description( 'Array of shared element UUIDs to attach to the page.' ),
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
        return Permission::can( 'page:add', $request->user() );
    }
}

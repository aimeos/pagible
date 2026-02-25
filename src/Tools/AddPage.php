<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Utils;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Models\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('create-page')]
#[Title('Create a new page within the page tree')]
#[Description('Creates a new page and adds it to the page tree. Returns the added page and its content as JSON object.')]
class AddPage extends Tool
{
    private int $numcalls = 0;


    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'page:view', $request->user() ) ) {
            throw new \Exception( 'Insufficient permissions' );
        }

        if( $this->numcalls > 0 ) {
            return Response::structured( ['error' => 'Only one page can be created at a time.'] );
        }

        $validated = $request->validate([
            'lang' => 'required|string|max:5',
            'name' => 'required|string|max:50',
            'title' => 'required|string|max:100',
            'summary' => 'required|string|max:200',
            'content' => 'required|string',
        ], [
            'lang.required' => 'You must specify a language code from the list of available locales. For example, "en" or "en-US".',
            'name.required' => 'You must specify a name for the page and it must not be longer than 50 characters.',
            'title.required' => 'You must specify a page title and it must not be longer than 100 characters.',
            'summary.required' => 'You must provide a SEO optimized summary for the page which is shorter than 200 characters.',
            'content.required' => 'You must provide content for the page in markdown format.',
        ] );


        $page = new Page();
        $pid = $request->get( 'parent_id' );

        /** @var Page|null $parent */
        $parent = $pid ? Page::find( $pid ) : null;
        $editor = (string) $request->user()?->name; // @phpstan-ignore-line property.notFound

        $elements = [[
            'id' => Utils::uid(),
            'type' => 'text',
            'group' => 'main',
            'data' => [
                'text' => $validated['content'],
            ]
        ]];
        $meta = [
            'meta-tags' => [
                'id' => Utils::uid(),
                'type' => 'meta-tags',
                'group' => 'basic',
                'data' => [
                    'description' => $validated['summary'],
                ]
            ]
        ];

        $page->tenant_id = \Aimeos\Cms\Tenancy::value();
        $page->editor = $editor;
        $page->fill( [
            'lang' => $validated['lang'],
            'name' => $validated['name'],
            'title' => $validated['title'],
            'path' => Utils::slugify( $validated['title'] ),
            'domain' => $parent?->latest?->data?->domain,
            'theme' => $parent?->latest?->data?->theme,
            'meta' => $meta,
            'content' => $elements,
        ] );

        $exclude = array_flip( ['content', 'config', 'meta', 'editor', 'relatedid', 'tenant_id'] );

        $version = [
            'lang' => $validated['lang'],
            'editor' => $editor,
            'data' => array_diff_key( $page->toArray(), $exclude ),
            'aux' => [
                'meta' => $meta,
                'content' => $elements,
            ]
        ];

        Cache::lock( 'cms_pages_' . \Aimeos\Cms\Tenancy::value(), 30 )->get( function() use ( $parent, $page, $version ) {
            DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $parent, $page, $version ) {

                if( $parent && ( $ref = Page::where( 'parent_id', $parent->id )->orderBy( '_lft', 'asc' )->first() ) ) {
                    $page->beforeNode( $ref );
                } elseif( $parent ) {
                    $page->appendToNode( $parent );
                }

                $page->save();
                $page->refresh();
                $page->versions()->create( $version );

            }, 3 );
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
            'summary' => $schema->string()
                ->description('Engaging meta description for the page content in the language of the page. Maximum 160 characters and in plaintext format.')
                ->required(),
            'content' => $schema->string()
                ->description('Page content in the language of the page and in markdown format.')
                ->required(),
            'parent_id' => $schema->string()
                ->description('ID of the parent page from the search-pages tool call where the new page will be added below.')
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

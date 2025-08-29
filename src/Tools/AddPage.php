<?php

namespace Aimeos\Cms\Tools;

use Prism\Prism\Tool;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Utils;


class AddPage extends Tool
{
    private int $numcalls = 0;


    public function __construct()
    {
        $this->as( 'create-page' )
            ->for( 'Creates a new page and adds it to the page tree. Returns the added page and its content as JSON object.' )
            ->withStringParameter( 'lang', 'ISO language code from get-locales tool call, e.g "en" or "en-GB".' )
            ->withStringParameter( 'title', 'Engaging and SEO optimized page title in the language of the page. Must be unique for each page and not longer than 60 characters.' )
            ->withStringParameter( 'name', 'Short name of the page for menus in the language of the page. Should not be longer than 30 characters.' )
            ->withStringParameter( 'content', 'Page content in the language of the page and in markdown format.' )
            ->withStringParameter( 'summary', 'Engaging meta description for the page content in the language of the page. Maximum 160 characters and in plaintext format.' )
            ->withNumberParameter( 'parent_id', 'ID of the parent page from the pages tool call the new page will be added below.', false )
            ->using( $this );
    }


    public function __invoke( string $lang, string $title, string $name, string $content, string $summary, ?int $parent_id = null ): string
    {
        if( $this->numcalls > 0 ) {
            return response()->json( ['error' => 'Only one page can be created at a time.'] );
        }

        $page = new Page();
        $parent = Page::find( $parent_id );
        $editor = Auth::user()?->name ?? request()->ip();
        $elements = [[
            'id' => Utils::uid(),
            'type' => 'text',
            'group' => 'main',
            'data' => [
                'text' => $content,
            ]
        ]];
        $meta = [
            'meta-tags' => [
                'id' => Utils::uid(),
                'type' => 'meta-tags',
                'group' => 'basic',
                'data' => [
                    'description' => $summary,
                ]
            ]
        ];

        $page->tenant_id = \Aimeos\Cms\Tenancy::value();
        $page->editor = $editor;
        $page->fill( [
            'lang' => $lang,
            'name' => $name,
            'title' => $title,
            'path' => Utils::slugify( $title ),
            'domain' => $parent?->latest?->data?->domain,
            'theme' => $parent?->latest?->data?->theme,
            'meta' => $meta,
            'content' => $elements,
        ] );

        $exclude = array_flip( ['content', 'config', 'meta', 'editor', 'relatedid', 'tenant_id'] );

        $version = [
            'lang' => $lang,
            'editor' => $editor,
            'data' => array_diff_key( $page->toArray(), $exclude ),
            'aux' => [
                'meta' => $meta,
                'content' => $elements,
            ]
        ];

        try
        {
            DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $parent, $page, $version ) {

                if( $parent )
                {
                    if( $ref = Page::where( 'parent_id', $parent->id )->orderBy( '_lft', 'asc' )->first() ) {
                        $page->beforeNode( $ref );
                    } elseif( $parent ) {
                        $page->appendToNode( $parent );
                    }
                }

                $page->save();
                $page->versions()->create( $version );

            }, 3 );
        }
        catch( \Illuminate\Database\UniqueConstraintViolationException $e )
        {
            return response()->json( $page );
        }

        $this->numcalls++;
        return response()->json( $page );
    }
}

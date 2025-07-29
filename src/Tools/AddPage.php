<?php

namespace Aimeos\Cms\Tools;

use Prism\Prism\Tool;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Aimeos\Cms\Models\Page;


class AddPage extends Tool
{
    private int $numcalls = 0;


    public function __construct()
    {
        $this->as( 'create-page' )
            ->for( 'Creates a new page and adds it to the page tree. Returns the added page and its content as JSON object.' )
            ->withStringParameter( 'lang', 'ISO language code from get-locales tool call, e.g "en" or "en-GB".' )
            ->withStringParameter( 'title', 'SEO optimized page title in the language of the page. Must be unique for each page' )
            ->withStringParameter( 'name', 'Short name of the page for menus in the language of the page. Should not be longer than 30 characters.' )
            ->withStringParameter( 'content', 'Page content in the language of the page and in markdown format.' )
            ->withNumberParameter( 'parent_id', 'ID of the parent page from the pages tool call the new page will be added below.', false )
            ->using( $this );
    }


    public function __invoke( string $lang, string $title, string $name, string $content, ?int $parent_id = null ): string
    {
        if( $this->numcalls > 0 ) {
            return response()->json( ['error' => 'Only one page can be created at a time.'] );
        }

        $page = new Page();
        $parent = Page::find( $parent_id );
        $editor = Auth::user()?->name ?? request()->ip();
        $elements = [[
            'id' => $this->uid(),
            'type' => 'text',
            'group' => 'main',
            'data' => [
                'text' => $content,
            ]
        ]];

        $page->tenant_id = \Aimeos\Cms\Tenancy::value();
        $page->editor = $editor;
        $page->fill( [
            'lang' => $lang,
            'name' => $name,
            'title' => $title,
            'path' => $this->slug( $title ),
            'domain' => $parent?->latest?->data?->domain,
            'theme' => $parent?->latest?->data?->theme,
            'content' => $elements,
        ] );

        $version = [
            'lang' => $lang,
            'editor' => $editor,
            'data' => array_diff_key( $page->toArray(), array_flip( ['content', 'config', 'meta'] ) ),
            'aux' => [
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


    /**
     * Generates a slug from the given title.
     *
     * @param string $title The title to generate a slug from
     * @return string The generated slug
     */
    protected function slug( string $title ): string
    {
        $title = preg_replace( '/[?&=%#@!$^*()+=\[\]{}|\\\\\"\'<>;:.,_\s]/u', '-', $title );
        $title = preg_replace( '/-+/', '-', $title );

        return mb_strtolower( trim( $title, '-' ) );
    }


    /**
     * Generates a unique ID for the page content element.
     *
     * This ID is a 6-character string that starts with a letter (A-Z, a-z) and is followed by 5 alphanumeric characters.
     * The first character is chosen from the first 52 characters of the base64 encoding,
     * while the remaining characters can be any of the 64 base64 characters.
     * The ID is based on the current time in milliseconds since a fixed epoch (2025-01-01T00:00:00Z),
     * ensuring that IDs are unique and non-repeating for approximately 70 years.
     *
     * @return string A unique 6-character ID for the page content element
     */
    protected function uid(): string
    {
        $base64 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
        $epoch = strtotime( '2025-01-01T00:00:00Z' ) * 1000;

        // IDs will repeat after ~70 years
        $value = ( ( (int) ( ( microtime( true ) * 1000 - $epoch ) / 256 ) ) << 3 );

        $id = '';
        for( $i = 0; $i < 6; $i++ )
        {
            // First character: only A-Z/a-z (index % 52), others: full 64-character set
            $index = ($value >> 6 * (5 - $i)) & 63;
            $id .= $base64[$i === 0 ? $index % 52 : $index];
        }

        return $id;
    }
}

<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Permission;
use GraphQL\Error\Error;


final class SavePage
{
    /**
     * @param  null  $rootValue
     * @param  array  $args
     */
    public function __invoke( $rootValue, array $args ) : Page
    {
        if( !Permission::can( 'page:save', Auth::user() ) ) {
            throw new Error( 'Insufficient permissions' );
        }

        $page = Page::withTrashed()->findOrFail( $args['id'] );

        DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $page, $args ) {

            $input = $this->sanitize( $args['input'] ?? [] );

            $data = array_diff_key( $input, array_flip( ['meta', 'config', 'content'] ) );
            $data = array_replace( (array) $page->latest?->data ?? [], $data );

            $aux = array_intersect_key( $input, array_flip( ['meta', 'config', 'content'] ) );
            $aux = array_replace( (array) $page->latest?->aux ?? [], $aux );

            $version = $page->versions()->create([
                'data' => array_map( fn( $v ) => $v ?? '', $data ),
                'editor' => Auth::user()?->name ?? request()->ip(),
                'lang' => $args['input']['lang'] ?? null,
                'aux' => $aux
            ]);

            $version->elements()->attach( $args['elements'] ?? [] );
            $version->files()->attach( $args['files'] ?? [] );

            $page->removeVersions();

        }, 3 );

        return $page;
    }


    protected function sanitize( array $input ) : array
    {
        if( !Permission::can( 'config:page', Auth::user() ) ) {
            unset( $input['config'] );
        }

        foreach( $input['content'] ?? [] as &$content )
        {
            if( @$content->type === 'html' && @$content->data->text ) {
                $content->data->text = \Aimeos\Cms\Utils::html( (string) $content->data->text );
            }
        }

        return $input;
    }
}

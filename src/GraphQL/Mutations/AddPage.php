<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Aimeos\Cms\Models\Page;


final class AddPage
{
    /**
     * @param  null  $rootValue
     * @param  array  $args
     */
    public function __invoke( $rootValue, array $args ) : Page
    {
        $page = new Page();

        DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $page, $args ) {

            $editor = Auth::user()?->name ?? request()->ip();

            $page->fill( $args['input'] ?? [] );
            $page->tenant_id = \Aimeos\Cms\Tenancy::value();
            $page->editor = $editor;

            if( isset( $args['ref'] ) ) {
                $page->beforeNode( Page::withTrashed()->findOrFail( $args['ref'] ) );
            }
            elseif( isset( $args['parent'] ) ) {
                $page->appendToNode( Page::withTrashed()->findOrFail( $args['parent'] ) );
            }

            $page->save();
            $page->files()->attach( $args['files'] ?? [] );
            $page->elements()->attach( $args['elements'] ?? [] );


            $data = $args['input'] ?? [];
            unset( $data['config'], $data['content'], $data['meta'] );

            $version = $page->versions()->create( [
                'data' => array_map( fn( $v ) => is_null( $v ) ? (string) $v : $v, $data ),
                'lang' => $args['input']['lang'] ?? null,
                'editor' => $editor,
                'aux' => [
                    'meta' => $args['input']['meta'] ?? new \stdClass(),
                    'config' => $args['input']['config'] ?? new \stdClass(),
                    'content' => $args['input']['content'] ?? [],
                ]
            ] );

            $version->elements()->attach( $args['elements'] ?? [] );
            $version->files()->attach( $args['files'] ?? [] );

        }, 3 );

        return $page;
    }
}

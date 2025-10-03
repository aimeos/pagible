<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Aimeos\Cms\Models\Element;


final class AddElement
{
    /**
     * @param  null  $rootValue
     * @param  array  $args
     */
    public function __invoke( $rootValue, array $args ) : Element
    {
        $element = new Element();

        DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $element, $args ) {

            $editor = Auth::user()?->name ?? request()->ip();

            $element->fill( $args['input'] ?? [] );
            $element->tenant_id = \Aimeos\Cms\Tenancy::value();
            $element->data = $args['input']['data'] ?? [];
            $element->editor = $editor;
            $element->save();

            $element->files()->attach( $args['files'] ?? [] );

            $version = $element->versions()->create( [
                'data' => array_map( fn( $v ) => is_null( $v ) ? (string) $v : $v, $args['input'] ?? [] ),
                'lang' => $args['input']['lang'] ?? null,
                'editor' => $editor,
            ] );

            $version->files()->attach( $args['files'] ?? [] );

        }, 3 );

        return $element;
    }
}

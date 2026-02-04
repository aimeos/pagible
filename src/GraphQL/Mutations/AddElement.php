<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Permission;
use GraphQL\Error\Error;


final class AddElement
{
    /**
     * @param  null  $rootValue
     * @param  array  $args
     */
    public function __invoke( $rootValue, array $args ) : Element
    {
        if( !Permission::can( 'element:add', Auth::user() ) ) {
            throw new Error( 'Insufficient permissions' );
        }

        if( @$args['input']['type'] === 'html' && @$args['input']['data']->text ) {
            $args['input']['data']->text = \Aimeos\Cms\Utils::html( (string) $args['input']['data']->text );
        }

        return DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $args ) {

            $element = new Element();

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

            return $element->refresh();
        }, 3 );
    }
}

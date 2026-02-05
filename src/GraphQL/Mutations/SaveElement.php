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


final class SaveElement
{
    /**
     * @param  null  $rootValue
     * @param  array  $args
     */
    public function __invoke( $rootValue, array $args ) : Element
    {
        if( !Permission::can( 'element:save', Auth::user() ) ) {
            throw new Error( 'Insufficient permissions' );
        }

        if( @$args['input']['type'] === 'html' && @$args['input']['data']->text ) {
            $args['input']['data']->text = \Aimeos\Cms\Utils::html( (string) $args['input']['data']->text );
        }

        return DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $args ) {

            $element = Element::withTrashed()->findOrFail( $args['id'] );

            $version = $element->versions()->create( [
                'data' => array_map( fn( $v ) => $v ?? '', $args['input'] ?? [] ),
                'editor' => Auth::user()?->name ?? request()->ip(),
                'lang' => $args['input']['lang'] ?? null,
            ] );

            $version->files()->attach( $args['files'] ?? [] );

            return $element->removeVersions()->fresh();
        }, 3 );
    }
}

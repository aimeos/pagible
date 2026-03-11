<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Permission;
use GraphQL\Error\Error;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


final class AddElement
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
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

            $editor = Auth::user()->name ?? request()->ip();
            $versionId = ( new Version )->newUniqueId();

            $element = new Element();
            $element->latest_id = $versionId;
            $element->fill( $args['input'] ?? [] );
            $element->data = $args['input']['data'] ?? [];
            $element->tenant_id = \Aimeos\Cms\Tenancy::value();
            $element->editor = $editor;
            $element->save();

            $element->files()->attach( $args['files'] ?? [] );

            $data = $args['input'] ?? [];
            ksort( $data );

            $version = $element->versions()->forceCreate( [
                'id' => $versionId,
                'data' => array_map( fn( $v ) => is_null( $v ) ? (string) $v : $v, $data ),
                'lang' => $args['input']['lang'] ?? null,
                'editor' => $editor,
            ] );

            $version->files()->attach( $args['files'] ?? [] );

            return $element;
        }, 3 );
    }
}

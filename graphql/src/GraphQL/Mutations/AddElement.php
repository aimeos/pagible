<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Validation;
use Illuminate\Support\Facades\Auth;


final class AddElement
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ) : Element
    {
        try {
            Validation::element( $args['input']['type'] ?? '' );
        } catch( \InvalidArgumentException $e ) {
            throw new \GraphQL\Error\Error( $e->getMessage() );
        }

        if( isset( $args['input']['data'] ) ) {
            Validation::html( $args['input']['type'] ?? '', $args['input']['data'] );
        }

        return Utils::transaction( function() use ( $args ) {

            $editor = Utils::editor( Auth::user() );
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

            return $element->setRelation( 'latest', $version );
        } );
    }
}

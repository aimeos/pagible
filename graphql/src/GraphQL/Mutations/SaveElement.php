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


final class SaveElement
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

            /** @var Element $element */
            $element = Element::withTrashed()->findOrFail( $args['id'] );
            $versionId = ( new Version )->newUniqueId();

            $version = $element->versions()->forceCreate( [
                'id' => $versionId,
                'data' => array_map( fn( $v ) => $v ?? '', $args['input'] ?? [] ),
                'editor' => Utils::editor( Auth::user() ),
                'lang' => $args['input']['lang'] ?? null,
            ] );

            $version->files()->attach( $args['files'] ?? [] );
            $element->forceFill( ['latest_id' => $version->id] )->save();

            $element->setRelation( 'latest', $version );
            return $element->removeVersions();
        } );
    }
}

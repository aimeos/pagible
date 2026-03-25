<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Validation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


final class SaveElement
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ) : Element
    {
        Validation::element( $args['input']['type'] ?? '' );

        if( @$args['input']['type'] === 'html' && @$args['input']['data']->text ) {
            $args['input']['data']->text = \Aimeos\Cms\Utils::html( (string) $args['input']['data']->text );
        }

        return DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $args ) {

            /** @var Element $element */
            $element = Element::withTrashed()->findOrFail( $args['id'] );
            $versionId = ( new Version )->newUniqueId();

            $version = $element->versions()->forceCreate( [
                'id' => $versionId,
                'data' => array_map( fn( $v ) => $v ?? '', $args['input'] ?? [] ),
                'editor' => Auth::user()->name ?? request()->ip(),
                'lang' => $args['input']['lang'] ?? null,
            ] );

            $version->files()->attach( $args['files'] ?? [] );
            $element->forceFill( ['latest_id' => $version->id] )->save();

            $element->setRelation( 'latest', $version );
            return $element->removeVersions();
        }, 3 );
    }
}

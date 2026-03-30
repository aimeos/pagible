<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Validation;
use GraphQL\Error\Error;
use Illuminate\Support\Facades\Auth;


final class SavePage
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ) : Page
    {
        return Utils::transaction( function() use ( $args ) {

            /** @var Page $page */
            $page = Page::withTrashed()->with( 'latest' )->findOrFail( $args['id'] );
            try {
                $input = Validation::page( $args['input'] ?? [], Auth::user() );
            } catch( \InvalidArgumentException $e ) {
                throw new Error( $e->getMessage() );
            }
            $versionId = ( new Version )->newUniqueId();

            $data = array_diff_key( $input, array_flip( ['meta', 'config', 'content'] ) );
            array_walk( $data, fn( &$v, $k ) => $v = !in_array( $k, ['related_id'] ) ? ( $v ?? '' ) : $v );
            $data = array_replace( (array) $page->latest?->data, $data );

            $aux = array_intersect_key( $input, array_flip( ['meta', 'config', 'content'] ) );
            $aux = array_replace( (array) $page->latest?->aux, $aux );

            $version = $page->versions()->forceCreate([
                'id' => $versionId,
                'data' => $data,
                'editor' => Utils::editor( Auth::user() ),
                'lang' => $args['input']['lang'] ?? null,
                'aux' => $aux
            ]);

            $version->elements()->attach( $args['elements'] ?? [] );
            $version->files()->attach( $args['files'] ?? [] );

            $page->forceFill( ['latest_id' => $version->id] )->save();

            $page->setRelation( 'latest', $version );
            return $page->removeVersions();
        } );
    }
}

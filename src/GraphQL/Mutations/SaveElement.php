<?php

namespace Aimeos\Cms\GraphQL\Mutations;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Models\Element;


final class SaveElement
{
    /**
     * @param  null  $rootValue
     * @param  array  $args
     */
    public function __invoke( $rootValue, array $args ) : Element
    {
        $element = Element::withTrashed()->findOrFail( $args['id'] );

        DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $element, $args ) {

            $version = $element->versions()->create( [
                'data' => array_map( fn( $v ) => $v ?? '', $args['input'] ?? [] ),
                'editor' => Auth::user()?->name ?? request()->ip(),
                'lang' => $args['input']['lang'] ?? null,
            ] );

            $version->files()->attach( $args['files'] ?? [] );

            $element->removeVersions();

        }, 3 );

        return $element;
    }
}

<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Validation;
use GraphQL\Error\Error;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


final class SavePage
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ) : Page
    {
        return DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $args ) {

            /** @var Page $page */
            $page = Page::withTrashed()->with( 'latest' )->findOrFail( $args['id'] );
            $input = $this->sanitize( $args['input'] ?? [] );
            $versionId = ( new Version )->newUniqueId();

            $data = array_diff_key( $input, array_flip( ['meta', 'config', 'content'] ) );
            array_walk( $data, fn( &$v, $k ) => $v = !in_array( $k, ['related_id'] ) ? ( $v ?? '' ) : $v );
            $data = array_replace( (array) $page->latest?->data, $data );

            $aux = array_intersect_key( $input, array_flip( ['meta', 'config', 'content'] ) );
            $aux = array_replace( (array) $page->latest?->aux, $aux );

            $version = $page->versions()->forceCreate([
                'id' => $versionId,
                'data' => $data,
                'editor' => Auth::user()->name ?? request()->ip(),
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


    /**
     * Sanitizes the input data based on user permissions and content type
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    protected function sanitize( array $input ) : array
    {
        if( !\Aimeos\Cms\Utils::isValidUrl( $input['to'] ?? null, false ) ) {
            $msg = 'Invalid URL "%s" in "to" field';
            throw new Error( sprintf( $msg, $input['to'] ?? '' ) );
        }

        if( !Permission::can( 'config:page', Auth::user() ) ) {
            unset( $input['config'] );
        }

        foreach( $input['content'] ?? [] as &$content )
        {
            if( @$content->type === 'html' && @$content->data->text ) {
                $content->data->text = \Aimeos\Cms\Utils::html( (string) $content->data->text );
            }
        }

        try {
            Validation::content( $input['content'] ?? [] );
            Validation::structured( $input['meta'] ?? new \stdClass(), 'meta' );
            Validation::structured( $input['config'] ?? new \stdClass(), 'config' );
        } catch( \InvalidArgumentException $e ) {
            throw new Error( $e->getMessage() );
        }

        return $input;
    }
}

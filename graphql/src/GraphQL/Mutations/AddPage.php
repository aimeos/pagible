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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;


final class AddPage
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ) : Page
    {
        return Cache::lock( 'cms_pages_' . \Aimeos\Cms\Tenancy::value(), 30 )->get( function() use ( $args ) {
            return DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $args ) {

                $args['input'] = $this->sanitize( $args['input'] ?? [] );
                $editor = Auth::user()->email ?? request()->ip();
                $versionId = ( new Version )->newUniqueId();

                $page = new Page();
                $page->fill( $args['input'] );
                $page->tenant_id = \Aimeos\Cms\Tenancy::value();
                $page->editor = $editor;

                if( isset( $args['ref'] ) ) {
                    /** @var Page $ref */
                    $ref = Page::withTrashed()->findOrFail( $args['ref'] );
                    $page->beforeNode( $ref );
                }
                elseif( isset( $args['parent'] ) ) {
                    /** @var Page $parent */
                    $parent = Page::withTrashed()->findOrFail( $args['parent'] );
                    $page->appendToNode( $parent );
                }

                $page->latest_id = $versionId;
                $page->save();

                $page->files()->attach( $args['files'] ?? [] );
                $page->elements()->attach( $args['elements'] ?? [] );

                $data = $args['input'];
                unset( $data['config'], $data['content'], $data['meta'] );

                $version = $page->versions()->forceCreate( [
                    'id' => $versionId,
                    'data' => array_map( fn( $v ) => is_null( $v ) ? (string) $v : $v, $data ),
                    'lang' => $args['input']['lang'] ?? null,
                    'editor' => $editor,
                    'aux' => [
                        'meta' => $args['input']['meta'] ?? new \stdClass(),
                        'config' => $args['input']['config'] ?? new \stdClass(),
                        'content' => $args['input']['content'] ?? [],
                    ]
                ] );

                $version->elements()->attach( $args['elements'] ?? [] );
                $version->files()->attach( $args['files'] ?? [] );

                return $page->setRelation( 'latest', $version );
            }, 3 );
        } );
    }


    /**
     * Sanitizes the input data by removing unauthorized fields and escaping HTML content
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

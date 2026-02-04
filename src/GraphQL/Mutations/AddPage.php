<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Permission;
use GraphQL\Error\Error;


final class AddPage
{
    /**
     * @param  null  $rootValue
     * @param  array  $args
     */
    public function __invoke( $rootValue, array $args ) : Page
    {
        if( !Permission::can( 'page:add', Auth::user() ) ) {
            throw new Error( 'Insufficient permissions' );
        }

        return Cache::lock( 'cms_pages_' . \Aimeos\Cms\Tenancy::value(), 30 )->get( function() use ( $args ) {
            return DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $args ) {

                $page = new Page();

                $args['input'] = $this->sanitize( $args['input'] ?? [] );
                $editor = Auth::user()?->name ?? request()->ip();

                $page->fill( $args['input'] ?? [] );
                $page->tenant_id = \Aimeos\Cms\Tenancy::value();
                $page->editor = $editor;

                if( isset( $args['ref'] ) ) {
                    $page->beforeNode( Page::withTrashed()->findOrFail( $args['ref'] ) );
                }
                elseif( isset( $args['parent'] ) ) {
                    $page->appendToNode( Page::withTrashed()->findOrFail( $args['parent'] ) );
                }

                $page->save();
                $page->files()->attach( $args['files'] ?? [] );
                $page->elements()->attach( $args['elements'] ?? [] );


                $data = $args['input'] ?? [];
                unset( $data['config'], $data['content'], $data['meta'] );

                $version = $page->versions()->create( [
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

                return $page->unsetRelations();
            }, 3 );
        } );
    }


    protected function sanitize( array $input ) : array
    {
        if( !Permission::can( 'config:page', Auth::user() ) ) {
            unset( $input['config'] );
        }

        foreach( $input['content'] ?? [] as &$content )
        {
            if( @$content->type === 'html' && @$content->data->text ) {
                $content->data->text = \Aimeos\Cms\Utils::html( (string) $content->data->text );
            }
        }

        return $input;
    }
}

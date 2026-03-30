<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Validation;
use GraphQL\Error\Error;
use Illuminate\Support\Facades\Auth;


final class AddPage
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ) : Page
    {
        return Utils::lockedTransaction( function() use ( $args ) {

                try {
                    $args['input'] = Validation::page( $args['input'] ?? [], Auth::user() );
                } catch( \InvalidArgumentException $e ) {
                    throw new Error( $e->getMessage() );
                }
                $editor = Utils::editor( Auth::user() );
                $versionId = ( new Version )->newUniqueId();

                $page = new Page();
                $page->fill( $args['input'] );
                $page->tenant_id = \Aimeos\Cms\Tenancy::value();
                $page->editor = $editor;

                Resource::position( $page, $args['ref'] ?? null, $args['parent'] ?? null );

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
        } );
    }
}

<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms\Controllers;

use Aimeos\Cms\FileResponse;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Scopes\Status;
use Aimeos\Cms\Tenancy;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;


class AssetController extends Controller
{
    /**
     * Delivers a private File after checking access to the page using it.
     */
    public function show( Request $request, string $page, string $file,
        int|string|null $variant = null ) : Response
    {
        $editor = false;
        $signed = $request->hasValidSignature();

        if( $signed && ( !$request->query->has( 'tenant' )
            || !hash_equals( Tenancy::value(), (string) $request->query( 'tenant' ) ) ) ) {
            abort( 403 );
        }

        if( !$signed )
        {
            $user = $request->user();
            $editor = Permission::can( 'page:view', $user ) && Permission::can( 'file:view', $user );
            $query = Page::select( 'id', 'tenant_id', 'latest_id' );

            if( !$editor ) {
                $query->withAccess( $user )
                    ->withGlobalScope( 'status', new Status() );
            }

            /** @var Page $owner */
            $owner = $query->findOrFail( $page );

            if( !$editor && $owner->access_exists && !$owner->access_allowed )
            {
                $user ? abort( 403 ) : throw new AuthenticationException();
            }

            if( !$this->attached( $owner, $file, $editor ) ) {
                abort( 404 );
            }
        }

        return FileResponse::make(
            $file,
            $variant,
            $editor,
            $signed && $request->query->has( 'expires' ) ? $request->integer( 'expires' ) : null,
        );
    }


    /**
     * Checks the published page references and the current draft for editors.
     */
    protected function attached( Page $page, string $file, bool $editor ) : bool
    {
        $db = DB::connection( config( 'cms.db', 'sqlite' ) );

        $refs = $db->table( 'cms_page_file' )->selectRaw( '1 as attached' )
            ->where( 'page_id', $page->id )->where( 'file_id', $file );
        $elements = $db->table( 'cms_element_file as ef' )->selectRaw( '1 as attached' )
            ->join( 'cms_page_element as pe', 'pe.element_id', '=', 'ef.element_id' )
            ->where( 'pe.page_id', $page->id )->where( 'ef.file_id', $file );

        $refs->unionAll( $elements );

        if( $editor && $page->latest_id )
        {
            $direct = $db->table( 'cms_version_file' )->selectRaw( '1 as attached' )
                ->where( 'version_id', $page->latest_id )->where( 'file_id', $file );
            $elements = $db->table( 'cms_version_element as ve' )->selectRaw( '1 as attached' )
                ->join( 'cms_element_file as ef', 'ef.element_id', '=', 've.element_id' )
                ->where( 've.version_id', $page->latest_id )->where( 'ef.file_id', $file );
            $versions = $db->table( 'cms_version_element as ve' )->selectRaw( '1 as attached' )
                ->join( 'cms_elements as e', 'e.id', '=', 've.element_id' )
                ->join( 'cms_version_file as vf', 'vf.version_id', '=', 'e.latest_id' )
                ->where( 've.version_id', $page->latest_id )
                ->where( 'e.tenant_id', $page->tenant_id )
                ->where( 'vf.file_id', $file );

            $refs->unionAll( $direct )->unionAll( $elements )->unionAll( $versions );
        }

        return $db->query()->fromSub( $refs, 'refs' )->exists();
    }
}

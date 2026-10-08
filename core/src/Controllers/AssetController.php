<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms\Controllers;

use Aimeos\Cms\FileResponse;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Permission;
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
            $query = Page::select( 'id', 'tenant_id' );

            // the status of the variants referencing the file is checked in attached()
            if( !$editor ) {
                $query->withAccess( $user );
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
     * Checks the published references of the page variants and their current drafts for editors.
     *
     * Visitors only get files of variants which are enabled and not in the trash.
     */
    protected function attached( Page $page, string $file, bool $editor ) : bool
    {
        $db = DB::connection( config( 'cms.db', 'sqlite' ) );

        $variants = $db->table( 'cms_page_variants' )->select( 'id' )
            ->where( 'page_id', $page->id )->where( 'tenant_id', $page->tenant_id )
            ->when( !$editor, fn( $q ) => $q->whereNull( 'deleted_at' )->whereIn( 'status', [1, 2] ) );

        $refs = $db->table( 'cms_page_file' )->selectRaw( '1 as attached' )
            ->whereIn( 'variant_id', $variants )->where( 'file_id', $file );
        $elements = $db->table( 'cms_element_file as ef' )->selectRaw( '1 as attached' )
            ->join( 'cms_page_element as pe', 'pe.element_id', '=', 'ef.element_id' )
            ->whereIn( 'pe.variant_id', $variants )->where( 'ef.file_id', $file );

        $refs->unionAll( $elements );

        if( $editor )
        {
            $latest = $db->table( 'cms_page_variants' )->select( 'latest_id' )
                ->where( 'page_id', $page->id )->where( 'tenant_id', $page->tenant_id )->whereNotNull( 'latest_id' );

            $direct = $db->table( 'cms_version_file' )->selectRaw( '1 as attached' )
                ->whereIn( 'version_id', $latest )->where( 'file_id', $file );
            $elements = $db->table( 'cms_version_element as ve' )->selectRaw( '1 as attached' )
                ->join( 'cms_element_file as ef', 'ef.element_id', '=', 've.element_id' )
                ->whereIn( 've.version_id', $latest )->where( 'ef.file_id', $file );
            $versions = $db->table( 'cms_version_element as ve' )->selectRaw( '1 as attached' )
                ->join( 'cms_elements as e', 'e.id', '=', 've.element_id' )
                ->join( 'cms_version_file as vf', 'vf.version_id', '=', 'e.latest_id' )
                ->whereIn( 've.version_id', $latest )
                ->where( 'e.tenant_id', $page->tenant_id )
                ->where( 'vf.file_id', $file );

            $refs->unionAll( $direct )->unionAll( $elements )->unionAll( $versions );
        }

        return $db->query()->fromSub( $refs, 'refs' )->exists();
    }
}

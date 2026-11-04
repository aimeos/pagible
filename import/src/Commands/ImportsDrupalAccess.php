<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Access;
use Aimeos\Cms\Import\Pages;
use Aimeos\Cms\Models\PageAccess;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Permission;


/**
 * Sets the access values of the imported pages and disables the pages of restricted Drupal nodes which failed to import.
 */
trait ImportsDrupalAccess
{
    /** @var array<string, array<int, string>>|null Sorted access values of the restricted pages by page ID */
    protected ?array $accessValues = null;
    /** @var array<int, string> URL paths of the nodes whose access is restricted in Drupal by node ID */
    protected array $restricted = [];


    /**
     * Sets the access values of the page from the view grants of Drupal's node access modules.
     *
     * The access of the pages isn't changed if no node access module is used or the node is
     * unpublished because Drupal doesn't use the grants of unpublished nodes. The access values of
     * all restricted pages are loaded once, so unchanged access doesn't cause any query.
     *
     * @param array<int, string>|false|null $values Access values from DrupalAccess::node() or FALSE to keep the access
     */
    protected function setAccess( PageView $page, array|false|null $values ): void
    {
        if( $values === false ) {
            return;
        }

        $this->accessValues ??= PageAccess::get( ['page_id', 'value'] )->groupBy( 'page_id' )
            ->map( fn( $rules ) => (array) PageAccess::values( $rules ) )->all();

        $id = (string) $page->id;
        $values = $values !== null ? Access::normalize( $values ) : null;

        if( ( $this->accessValues[$id] ?? null ) === $values ) {
            return;
        }

        Permission::system( fn() => PageAccess::set( [$id], $values ) );

        if( $values !== null ) {
            $this->accessValues[$id] = $values;
        } else {
            unset( $this->accessValues[$id] );
        }
    }


    /**
     * Disables the pages of previous imports whose nodes are restricted in Drupal but failed to import.
     *
     * Otherwise, their pages would stay public if the node was public before and the access
     * restrictions couldn't be imported.
     */
    protected function protect(): void
    {
        foreach( $this->restricted as $nid => $slug )
        {
            $page = $this->createdPages->has( $nid ) ? null : $this->pages->path( $slug );

            if( !$page || $page->editor !== $this->editor || $page->to !== '' || $page->tag === 'root' ) {
                continue;
            }

            foreach( PageVariant::where( 'page_id', $page->id )->where( 'editor', $this->editor )->where( 'status', '!=', 0 )->pluck( 'lang' ) as $lang )
            {
                if( $variant = PageView::language( (string) $lang, true )->find( $page->id ) ) {
                    $this->disable( $variant );
                }
            }
        }
    }


    /**
     * Publishes a version of the page which disables it.
     */
    protected function disable( PageView $page ): void
    {
        if( !( $version = $page->versions()->where( 'published', true )->first() ) ) {
            return;
        }

        $data = array_replace( array_diff_key( (array) $version->data, ['scheduled' => null] ), ['status' => 0] );
        $aux = (array) json_decode( (string) json_encode( $version->aux ), true );

        Pages::publish( $page, $data, $aux, $version->files()->allRelatedIds()->map( strval( ... ) )->all(),
            $version->elements()->allRelatedIds()->map( strval( ... ) )->all(), (string) $page->lang, $this->editor );

        $this->warn( '  Disabled /' . ltrim( (string) $page->path, '/' ) . " [{$page->lang}] because its Drupal node is restricted but failed to import" );
    }
}

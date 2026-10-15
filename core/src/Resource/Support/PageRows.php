<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Resource\Support;

use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Query\PageBuilder;
use Aimeos\Cms\Tenancy;
use Illuminate\Support\Collection;


/**
 * Low-level writes of page variant records and versions shared by the resource classes.
 *
 * @internal Use the Pages and Variants resource classes instead
 */
final class PageRows
{
    /**
     * Saves the changed variant values of the page view to its variant.
     *
     * @param PageView $page Existing page view
     * @param array<string, mixed> $values Additional column/value pairs of the page view to save
     * @return PageView Same page view with the saved values
     */
    public static function save( PageView $page, array $values = [] ) : PageView
    {
        $page->forceFill( $values );

        $dirty = $page->getDirty();

        if( empty( PageBuilder::variantValues( $dirty ) ) ) {
            return $page;
        }

        $original = $page->getRawOriginal();

        $variant = $page->toVariant();
        $variant->save();

        // the source language follows the language of the source variant
        if( isset( $dirty['lang'] ) && ( $original['lang'] ?? null ) === ( $original['source'] ?? null ) ) {
            $page->setAttribute( 'source', $dirty['lang'] );
        }

        $page->setAttribute( 'updated_at', $variant->getAttributes()['updated_at'] ?? null );
        $page->syncChanges();
        $page->syncOriginal();

        return $page;
    }


    /**
     * Returns the complete record of a new page variant for inserting it.
     *
     * Missing values are set to the defaults of the variants, a new unique ID,
     * the current tenant and the current time. NULL values are skipped.
     *
     * @param string $pageId ID of the page the variant belongs to
     * @param array<string, mixed> $values Attribute values which are converted like in the PageVariant model
     * @param bool $raw TRUE if the values are column values as stored in the database
     * @return array<string, mixed> Record of the page variant
     */
    public static function row( string $pageId, array $values, bool $raw = false ) : array
    {
        $values = array_filter( $values, fn( $v ) => $v !== null );

        $variant = new PageVariant();
        $variant->setRawAttributes( array_replace( $variant->getAttributes(), ['tenant_id' => Tenancy::value()], $raw ? $values : [] ) );
        $variant->forceFill( ( $raw ? [] : $values ) + ['page_id' => $pageId] );

        if( !$variant->getKey() ) {
            $variant->setAttribute( 'id', $variant->newUniqueId() );
        }

        if( !$variant->getAttribute( 'created_at' ) ) {
            $variant->setCreatedAt( $variant->freshTimestamp() );
        }

        if( !$variant->getAttribute( 'updated_at' ) ) {
            $variant->setUpdatedAt( $variant->freshTimestamp() );
        }

        return $variant->getAttributes();
    }


    /**
     * Returns the language and the variant attributes contained in the page data.
     *
     * @param array<string, mixed> $data Page data
     * @return array<string, mixed> Language and variant attribute values
     */
    public static function data( array $data ) : array
    {
        return array_intersect_key( $data, array_flip( ['lang', ...PageVariant::DATA_FIELDS] ) );
    }


    /**
     * Adds a new version to the page variant and saves the page.
     *
     * @param PageView $page Page with the variant the version belongs to
     * @param array<string, mixed> $data Version data
     * @param array<string, mixed> $aux Content, meta and config
     * @param string $editor Name of the editing user
     * @param array<string, array<string>>|null $refs File and element references or NULL to copy the ones of the previous version
     * @param array<string, mixed>|null $diffs Merge differences for the change info
     * @param string|null $lang Language of the version, the one of the data or the page by default
     * @return PageView Page with the new version as "latest"
     */
    public static function addVersion( PageView $page, array $data, array $aux, string $editor,
        ?array $refs, ?array $diffs = null, ?string $lang = null ) : PageView
    {
        $page->newDraft( [
            'data' => $data,
            'lang' => $lang ?? $data['lang'] ?? $page->lang,
            'editor' => $editor,
            'aux' => $aux,
        ], $refs, $diffs );

        return self::save( $page );
    }


    /**
     * Returns the pages of the list.
     *
     * @param iterable<mixed> $items List of models
     * @return Collection<int, PageView> List of pages
     */
    public static function views( iterable $items ) : Collection
    {
        $list = [];

        foreach( $items as $item ) {
            if( $item instanceof PageView ) {
                $list[] = $item;
            }
        }

        return collect( $list );
    }


    /**
     * Inserts the record of a new page variant without a version.
     *
     * @param PageView $source Page with its source variant
     * @param array<string, mixed> $data Page data including the language, domain and path of the variant
     * @param array<string, string> $hashes Hashes of the new variant
     * @param bool $stale If the new variant needs an update
     * @param string $editor Name of the editing user
     * @return PageView Page with the new variant
     */
    public static function insert( PageView $source, array $data, array $hashes, bool $stale, string $editor ) : PageView
    {
        $variant = self::row( (string) $source->id, self::data( $data ) + [
            'hashes' => $hashes,
            'stale' => $stale,
            'editor' => $editor,
        ] );

        PageVariant::withoutGlobalScopes()->toBase()->insert( $variant );

        // the page view is built from the page of the source and the new variant instead of reading it again,
        // the variant columns left out of the inserted record because they are NULL must replace the source values
        $values = array_fill_keys( [...PageVariant::FIELDS, 'id', 'deleted_at'], null );

        return ( new PageView() )->newFromBuilder( $source->getAttributes() )
            ->synced( ( new PageVariant() )->setRawAttributes( array_replace( $values, $variant ) ) );
    }
}

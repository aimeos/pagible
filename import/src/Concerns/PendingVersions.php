<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Aimeos\Cms\Import\Pages;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Models\Version;


/**
 * Saves the pending versions of the imported pages like drafts and scheduled publications.
 */
trait PendingVersions
{
    /** @var array<string, int>|null IDs of the page variants with pending versions of previous imports */
    protected ?array $pendingVariants = null;


    /**
     * Replaces the pending versions of previous imports by the given versions unless they are the same.
     *
     * @param array<int, array{0: array<string, mixed>, 1: array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>, elementIds?: array<int, string>, meta?: array<string, mixed>}, 2: int|null}> $versions Page data, content and publication timestamps
     */
    protected function saveVersions( PageView $page, array $versions ): void
    {
        // the pending versions are only fetched for pages which have some
        if( !$versions && !isset( $this->pendingVariants()[(string) $page->variant_id] ) ) {
            return;
        }

        $existing = $page->versions()->where( 'published', false )->where( 'editor', $this->editor )
            ->reorder()->orderBy( 'created_at' )->orderBy( 'id' )->get();

        $same = $existing->count() === count( $versions ) && $existing->values()->every( function( $version, $idx ) use ( $versions ) {
            [$data, $content, $at] = $versions[$idx];
            $aux = ['content' => $content['elements'], 'meta' => $content['meta'] ?? []];

            return Pages::same( $version, $data, $aux, $at ? date( 'Y-m-d H:i:s', $at ) : null );
        } );

        if( $same )
        {
            if( ( $last = $existing->last() ) && $page->latest_id !== $last->id ) {
                PageVariant::whereKey( $page->variant_id )->toBase()->update( ['latest_id' => $last->id] );
            }

            return;
        }

        $existing->each( fn( $version ) => $version->delete() );

        foreach( $versions as [$data, $content, $at] )
        {
            $aux = ['content' => $content['elements']] + ( !empty( $content['meta'] ) ? ['meta' => $content['meta']] : [] );
            Pages::version( $page, $data, $aux, $content['fileIds'], $content['elementIds'] ?? [], (string) $page->lang, $this->editor, $at ? date( 'Y-m-d H:i:s', $at ) : null );
        }

        if( !$versions && $existing->isNotEmpty() && ( $id = $page->versions()->where( 'published', true )->value( 'id' ) ) ) {
            PageVariant::whereKey( $page->variant_id )->toBase()->update( ['latest_id' => $id] );
        }
    }


    /**
     * Returns the IDs of the page variants with pending versions of previous imports.
     *
     * They are fetched once so pages without drafts or scheduled versions don't need a query each.
     *
     * @return array<string, int> Positions by page variant ID
     */
    protected function pendingVariants(): array
    {
        return $this->pendingVariants ??= Version::where( 'versionable_type', PageVariant::class )->where( 'published', false )
            ->where( 'editor', $this->editor )->distinct()->pluck( 'versionable_id' )->map( strval( ... ) )->flip()->all();
    }
}

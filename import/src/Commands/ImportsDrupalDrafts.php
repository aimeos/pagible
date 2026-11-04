<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Import\Pages;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Models\Version;
use Illuminate\Support\Str;


/**
 * Imports the newer draft revisions and the scheduled publication of the Drupal nodes as pending page versions.
 */
trait ImportsDrupalDrafts
{
    /** @var array<int, array<string, \stdClass>>|null Newest draft revisions by node ID and Drupal language code */
    protected ?array $drafts = null;
    /** @var array<string, int>|null IDs of the page variants with pending versions of previous imports */
    protected ?array $pendingVariants = null;


    /**
     * Creates the scheduled versions of the Scheduler module and the newer draft of the Content Moderation module.
     *
     * The publication and unpublication dates of the Scheduler module become versions which are
     * published by the "cms:publish" command at that time. A newer draft revision, e.g. of the
     * Content Moderation module, becomes the latest version of the page which isn't published.
     * Pending versions of previous imports are replaced if they changed.
     *
     * @param object{nid: int, type: string, title: string, status: int, langcode?: string, vid?: int, publish_on?: int|null, unpublish_on?: int|null} $node
     * @param array<string, mixed> $data Page data of the published version
     * @param array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>, elementIds?: array<int, string>, meta?: array<string, mixed>} $content Content of the published version
     * @param bool $fallback TRUE to use field values of another language if none exist for the language
     */
    protected function pending( PageView $page, object $node, array $data, array $content, bool $fallback ): void
    {
        $versions = [];
        $draft = $this->draft( $page, $node, $data, $fallback );
        $base = $draft ?? [$data, $content];

        if( ( $at = (int) ( $node->publish_on ?? 0 ) ) > time() ) {
            $versions[] = $base = [array_replace( $base[0], ['status' => 1] ), $base[1], $at];
        } elseif( $draft ) {
            $versions[] = [$draft[0], $draft[1], null];
            $base = [$data, $content];
        }

        if( ( $at = (int) ( $node->unpublish_on ?? 0 ) ) > time() ) {
            $versions[] = [array_replace( $base[0], ['status' => 0] ), $base[1], $at];
        }

        $this->saveVersions( $page, $versions );
    }


    /**
     * Returns the page data and content of a newer draft revision of the node.
     *
     * @param object{nid: int, type: string, title: string, langcode?: string, vid?: int} $node
     * @param array<string, mixed> $data Page data of the published version
     * @param bool $fallback TRUE to use field values of another language if none exist for the language
     * @return array{0: array<string, mixed>, 1: array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>, elementIds: array<int, string>, meta: array<string, mixed>}}|null Page data and content or NULL if there's no newer draft
     */
    protected function draft( PageView $page, object $node, array $data, bool $fallback ): ?array
    {
        $langcode = $node->langcode ?? '';

        if( !isset( $node->vid ) || !( $revision = $this->drafts()[$node->nid][$langcode] ?? null ) ) {
            return null;
        }

        $vid = (int) $revision->vid;
        $title = (string) $revision->title;
        // meta title like "[node:title] | [site:name]" of the draft revision
        $metaTitle = $this->revision( $node->nid, $vid, $title, fn() => $this->meta->tags( $node, $langcode )['title'] ?? '' );
        $draft = array_replace( $data, array_filter( [
            'name' => $data['name'] === $node->title ? $title : null,
            'title' => $metaTitle !== '' ? Str::limit( $metaTitle, 254, '…', true ) : $title,
        ] ) );
        $content = $this->nodeContent( $node, $langcode, $fallback, $vid, $title );

        // drafts which only differ in their revision aren't imported
        if( Pages::unchanged( $page, $draft, ['content' => $content['elements'], 'meta' => $content['meta']] ) ) {
            return null;
        }

        return [$draft, $content];
    }


    /**
     * Returns the newest revisions which are newer than the current revisions of the nodes.
     *
     * They are fetched at once for all nodes to avoid one query per node and language.
     *
     * @return array<int, array<string, \stdClass>> Revision rows with "vid" and "title" by node ID and Drupal language code
     */
    protected function drafts(): array
    {
        if( $this->drafts !== null ) {
            return $this->drafts;
        }

        $this->drafts = [];

        if( !$this->data->hasTable( 'node_field_revision' ) || !$this->data->hasColumn( 'node_field_data', 'vid' ) ) {
            return $this->drafts;
        }

        foreach( $this->reader->drafts( $this->nodes->keys()->map( fn( $id ) => (int) $id )->all() ) as $row ) {
            $this->drafts[(int) $row->nid][(string) $row->langcode] = $row;
        }

        // the field values of the drafts are fetched together in the import order of the nodes
        $this->data->drafts( array_map( fn( $rows ) => array_values( array_unique( array_map( fn( $row ) => (int) $row->vid, $rows ) ) ), $this->drafts ) );

        return $this->drafts;
    }


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

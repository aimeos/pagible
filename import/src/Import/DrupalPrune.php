<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Aimeos\Cms\DB;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\Version;
use Illuminate\Support\Collection;


/**
 * Reports the pages, translations, shared elements and files of previous imports which weren't
 * updated by this import and resolves the ones which can be moved to the trash.
 */
class DrupalPrune
{
    /**
     * @param string $editor Editor name of the imported records
     * @param \Closure(string): void $warn Shows a warning
     */
    public function __construct( protected string $editor, protected \Closure $warn )
    {
    }


    /**
     * Returns the compact key of a record ID for the lists of saved records.
     *
     * UUIDs are stored as 16 bytes instead of 36 characters to reduce the memory used by large imports.
     */
    public static function key( string $id ): string
    {
        $hex = str_replace( '-', '', $id );

        // other IDs are prefixed, so they never match a compact UUID
        return strlen( $hex ) === 32 && ctype_xdigit( $hex ) && ( $bin = hex2bin( $hex ) ) !== false ? $bin : '#' . $id;
    }


    /**
     * Lists the shared elements of previous imports which weren't updated by this import.
     *
     * Shared elements are only updated if the pages are rendered. Shared elements of other Domain
     * Access domains aren't rendered by this import.
     *
     * @param array<int, string> $domains Domains of the imported pages
     * @param array<int, string> $saved IDs of the shared elements saved by this import
     * @param bool $others TRUE to skip the shared elements used by pages of other domains
     * @return Collection<int, Element> Stale shared elements
     */
    public function elements( array $domains, array $saved, bool $others ): Collection
    {
        $others = $others ? DB::connection()->table( 'cms_page_element' )
            ->whereIn( 'variant_id', PageVariant::whereNotIn( 'domain', $domains )->select( 'id' ) )->select( 'element_id' ) : null;
        // the saved elements are skipped while reading because the list can be too long for the query
        $saved = array_flip( $saved );
        $elements = Element::where( 'editor', $this->editor )
            ->when( $others, fn( $query ) => $query->whereNotIn( 'id', $others ) )
            ->orderBy( 'name' )->orderBy( 'lang' )->select( ['id', 'name', 'lang'] )->cursor()
            ->filter( fn( $element ) => !isset( $saved[(string) $element->id] ) )->values()->collect();

        if( $elements->isNotEmpty() )
        {
            ( $this->warn )( "{$elements->count()} shared elements of previous imports weren't updated, their Drupal blocks may be deleted, hidden or shorter now:" );

            foreach( $elements as $element ) {
                ( $this->warn )( "  {$element->name} [{$element->lang}]" );
            }
        }

        return $elements;
    }


    /**
     * Returns the stale pages, shared elements and the files of previous imports which aren't used any more and can be moved to the trash.
     *
     * @param Collection<int, PageVariant> $stale Stale page variants
     * @param Collection<int, Element> $elements Stale shared elements
     * @param array<int, string> $created IDs of the files created or used by this import
     * @param int $failed Number of failed imports, nothing is trashed if imports failed
     * @return array{pages: array<int, string>, translations: array<string, array<int, string>>, elements: array<int, string>, files: array<int, string>, count: int}|null IDs of the pages, the page IDs of the translations by language, the IDs of the shared elements and files and the number of pages and translations or NULL if nothing is trashed
     */
    public function trashable( Collection $stale, Collection $elements, array $created, int $failed ): ?array
    {
        // files of previous imports which aren't used by this import
        $created = array_flip( $created );
        $files = File::where( 'editor', $this->editor )->select( 'id' )->toBase()->cursor()->map( fn( $row ) => (string) $row->id )
            ->filter( fn( $id ) => !isset( $created[$id] ) )->values()->all();

        if( $stale->isEmpty() && $elements->isEmpty() && $files === [] ) {
            return null;
        }

        if( $failed ) {
            ( $this->warn )( "Nothing moved to the trash because {$failed} imports failed." );
            return null;
        }

        return $this->resolve( $stale, $elements, $files );
    }


    /**
     * Lists the page variants of previous imports which weren't updated by this import.
     *
     * They may have been deleted or unpublished in Drupal. Pages changed in Pagible since aren't
     * listed because they have another editor.
     *
     * @param array<int, string> $domains Domains of the imported pages
     * @param array<string, bool> $saved Compact IDs of the page variants saved by this import as keys, see key()
     * @return Collection<int, PageVariant> Stale page variants
     */
    public function stale( array $domains, array $saved ): Collection
    {
        // variants of trashed pages aren't listed, the saved ones are skipped while reading because the list can be too long for the query
        $stale = PageVariant::whereHas( 'page', fn( $query ) => $query->withoutTrashed() )->whereIn( 'domain', $domains )->where( 'editor', $this->editor )
            ->where( 'tag', '!=', 'root' )->orderBy( 'path' )->select( ['id', 'page_id', 'path', 'lang'] )->cursor()
            ->filter( fn( $variant ) => !isset( $saved[self::key( (string) $variant->id )] ) )->values()->collect();

        if( $stale->isNotEmpty() )
        {
            ( $this->warn )( "{$stale->count()} pages of previous imports weren't updated, they may be deleted or unpublished in Drupal, not selected or failed:" );

            foreach( $stale as $page ) {
                ( $this->warn )( '  /' . ltrim( (string) $page->path, '/' ) . " [{$page->lang}]" );
            }
        }

        return $stale;
    }


    /**
     * Resolves the stale pages, translations, shared elements and files which can be moved to the trash.
     *
     * Pages are only trashed if all their variants are stale and all their child pages are trashed
     * too because trashing a page trashes its subtree. Otherwise, only their stale translations are
     * trashed. Shared elements still referenced by kept pages aren't trashed. Later imports restore
     * them if the Drupal nodes or blocks are available again.
     *
     * @param Collection<int, PageVariant> $stale Stale page variants
     * @param Collection<int, Element> $elements Stale shared elements
     * @param array<int, string> $files IDs of the files of previous imports which aren't used by this import
     * @return array{pages: array<int, string>, translations: array<string, array<int, string>>, elements: array<int, string>, files: array<int, string>, count: int}
     */
    protected function resolve( Collection $stale, Collection $elements, array $files ): array
    {
        // the lists of IDs can be too long for a query, so they are compared afterwards
        $variantIds = array_flip( $stale->pluck( 'id' )->map( strval( ... ) )->all() );
        $allIds = $stale->pluck( 'page_id' )->map( strval( ... ) )->unique()->values()->all();

        // pages with other variants, e.g. changed in Pagible since, are kept
        $kept = Database::chunked( $allIds, fn( $ids ) => PageVariant::whereIn( 'page_id', $ids )->get( ['id', 'page_id'] ) )
            ->filter( fn( $variant ) => !isset( $variantIds[(string) $variant->id] ) )->pluck( 'page_id' )->map( strval( ... ) )->all();
        $pageIds = array_values( array_diff( $allIds, $kept ) );

        do {
            $set = array_flip( $pageIds );
            $parents = Database::chunked( $pageIds, fn( $ids ) => Page::whereIn( 'parent_id', $ids )->toBase()->get( ['id', 'parent_id'] ) )
                ->filter( fn( $page ) => !isset( $set[(string) $page->id] ) )->pluck( 'parent_id' )->map( strval( ... ) )->unique()->values()->all();
            $pageIds = array_values( array_diff( $pageIds, $parents ) );
        } while( $parents );

        $sources = Database::chunked( $allIds, fn( $ids ) => Page::whereKey( $ids )->toBase()->get( ['id', 'source'] ) )
            ->mapWithKeys( fn( $page ) => [(string) $page->id => (string) $page->source] )->all();
        $set = array_flip( $pageIds );
        [$pages, $rest] = $stale->partition( fn( $variant ) => isset( $set[(string) $variant->page_id] ) )->all();
        $translations = $rest->filter( fn( $variant ) => $variant->lang !== ( $sources[$variant->page_id] ?? '' ) );

        foreach( $rest->diffKeys( $translations ) as $variant ) {
            ( $this->warn )( '  Kept /' . ltrim( (string) $variant->path, '/' ) . " [{$variant->lang}] (used by translations or child pages which are kept)" );
        }

        // elements used by kept pages aren't trashed
        $trashed = [...$pages->pluck( 'id' )->all(), ...$translations->pluck( 'id' )->all()];
        $skip = array_flip( array_map( strval( ... ), $trashed ) );
        $used = Database::chunked( $elements->pluck( 'id' )->all(), fn( $ids ) => DB::connection()->table( 'cms_page_element' )->whereIn( 'element_id', $ids )
            ->whereIn( 'variant_id', PageVariant::whereHas( 'page', fn( $query ) => $query->withoutTrashed() )->select( 'id' ) )
            ->get( ['element_id', 'variant_id'] )
        )->filter( fn( $row ) => !isset( $skip[(string) $row->variant_id] ) )->pluck( 'element_id' )->map( strval( ... ) )->unique()->values()->all();

        $used = array_flip( $used );
        [$keptElements, $unused] = $elements->partition( fn( $element ) => isset( $used[(string) $element->id] ) )->all();

        foreach( $keptElements as $element ) {
            ( $this->warn )( "  Kept {$element->name} [{$element->lang}] (used by pages which are kept)" );
        }

        $translationIds = [];

        foreach( $translations as $variant ) {
            $translationIds[(string) $variant->lang][] = (string) $variant->page_id;
        }

        $elementIds = $unused->pluck( 'id' )->map( strval( ... ) )->values()->all();

        return [
            'pages' => $pageIds,
            'translations' => $translationIds,
            'elements' => $elementIds,
            'files' => $this->unusedFiles( $files, $trashed, $elementIds ),
            'count' => $pages->count() + $translations->count(),
        ];
    }


    /**
     * Returns the IDs of the files which aren't used by pages, shared elements or pending versions any more.
     *
     * Files used by pages and shared elements which are trashed by this or previous imports aren't
     * used any more. Files of pages and shared elements which were changed in Pagible before they
     * were trashed are still considered as used because editors may restore them.
     *
     * @param array<int, string> $files IDs of the files of previous imports which aren't used by this import
     * @param array<int, string> $variantIds IDs of the page variants trashed by this import
     * @param array<int, string> $elementIds IDs of the shared elements trashed by this import
     * @return array<int, string> IDs of the unused files
     */
    protected function unusedFiles( array $files, array $variantIds, array $elementIds ): array
    {
        $db = DB::connection();
        // trashing doesn't change the editor of pages but the one of translations and shared elements
        $editors = [$this->editor, 'system'];
        $used = [];
        // the trashed variants and elements are skipped afterwards because the lists can be too long for the query
        $skipVariants = array_flip( $variantIds );
        $skipElements = array_flip( $elementIds );

        // the records using the files are looked up by their keys instead of comparing all records for each chunk of files
        $variants = PageVariant::withTrashed()->whereColumn( 'cms_page_variants.id', 'cms_page_file.variant_id' )->where( fn( $query ) => $query
            ->where( fn( $query ) => $query->whereNull( 'deleted_at' )->whereHas( 'page', fn( $query ) => $query->withoutTrashed() ) )
            ->orWhereNotIn( 'editor', $editors ) )->select( 'id' )->toBase();
        $elements = Element::withTrashed()->whereColumn( 'cms_elements.id', 'cms_element_file.element_id' )->where( fn( $query ) => $query
            ->whereNull( 'deleted_at' )->orWhereNotIn( 'editor', $editors ) )->select( 'id' )->toBase();
        $versions = Version::whereColumn( 'cms_versions.id', 'cms_version_file.version_id' )->where( 'published', false )->select( 'id' )->toBase();

        foreach( array_chunk( $files, DB::IN_SIZE ) as $ids )
        {
            $used[] = $db->table( 'cms_page_file' )->whereIn( 'file_id', $ids )->whereExists( $variants )->get( ['file_id', 'variant_id'] )
                ->filter( fn( $row ) => !isset( $skipVariants[(string) $row->variant_id] ) )->pluck( 'file_id' )->all();
            $used[] = $db->table( 'cms_element_file' )->whereIn( 'file_id', $ids )->whereExists( $elements )->get( ['file_id', 'element_id'] )
                ->filter( fn( $row ) => !isset( $skipElements[(string) $row->element_id] ) )->pluck( 'file_id' )->all();
            $used[] = $db->table( 'cms_version_file' )->whereIn( 'file_id', $ids )->whereExists( $versions )->pluck( 'file_id' )->all();
        }

        return array_values( array_diff( $files, array_map( strval( ... ), array_merge( ...$used ) ) ) );
    }
}

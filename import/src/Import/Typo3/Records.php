<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Typo3;

use Aimeos\Cms\Import\Database;
use Illuminate\Support\Collection;


/**
 * Selects the TYPO3 content records of a page, expands the content shortcuts and
 * marks the records which are stored as shared Pagible elements.
 */
class Records
{
    /** Maximum number of cached content records referenced by shortcuts */
    protected const CACHE_SIZE = 10000;

    /** @var array<int, mixed> Content records in default language referenced by shortcuts or NULL by UID */
    protected array $targetRecords = [];

    /** @var array<string, mixed> Translations of the content records referenced by shortcuts or NULL by "<lang UID>:<UID>" */
    protected array $targetOverlays = [];

    /** UID of the page whose content records in default language were fetched last */
    protected ?int $lastPage = null;

    /** @var Collection<int, mixed> Content records in default language of the page fetched last */
    protected Collection $lastRecords;

    /** @var Collection<int|string, Collection<int, mixed>>|null Translated content records of the page fetched last grouped by language UID */
    protected ?Collection $lastTranslated = null;

    /** @var array<int, Collection<int, mixed>> Prefetched content records in default language of the upcoming pages by page UID */
    protected array $contents = [];

    /** @var array<int, Collection<int, mixed>> Prefetched translated content records of the upcoming pages by page UID */
    protected array $translations = [];

    /** @var array<int, bool>|null UIDs of the content records referenced by shortcuts */
    protected ?array $referenced = null;

    /** @var array<int, bool>|null UIDs of the pages whose content is shown by other pages */
    protected ?array $sourcePages = null;

    /** @var array<int, list<int>>|null Targets of the content shortcuts by shortcut UID */
    protected ?array $shortcutTargets = null;

    /** @var array<int, list<int>> UIDs of the content shortcuts by page UID */
    protected array $shortcutPages = [];

    /** TYPO3 page whose content records are returned by page() */
    protected ?object $current = null;


    /**
     * The content records are fetched for a limited number of pages because all content of large sites doesn't fit into memory.
     *
     * @param Collection<int, mixed> $pages TYPO3 pages keyed by UID
     * @param \Closure(array<int, int>): Collection<int, mixed> $content Fetches the content records of the page UIDs in default language ordered by sorting
     * @param \Closure(array<int, int>): Collection<int, mixed> $translated Fetches the translated content records of all languages of the page UIDs ordered by sorting
     * @param \Closure(array<int, int>): Collection<int, mixed> $records Fetches the content records in default language keyed by UID
     * @param Collection<int, mixed> $shortcuts Content shortcuts in default language with their UIDs, pages and targets
     * @param Layouts $layouts Orders the content records by the backend layouts of the pages
     * @param (\Closure(int, ?object): string)|null $mode Returns the TYPO3 fallback type ("strict", "fallback" or "free") of the language UID in the site of the TYPO3 page, "strict" by default
     * @param (\Closure(array<int, int>, int): Collection<int, mixed>)|null $overlays Fetches the translations of the content records in default language in the language keyed by their UID
     */
    public function __construct( protected Collection $pages, protected \Closure $content, protected \Closure $translated,
        protected \Closure $records, protected Collection $shortcuts, protected Layouts $layouts,
        protected ?\Closure $mode = null, protected ?\Closure $overlays = null )
    {
    }


    /**
     * Fetches the content records of the upcoming pages at once and replaces the ones fetched before.
     *
     * @param iterable<object> $t3Pages TYPO3 pages whose content records in default language are fetched
     * @param iterable<object> $translatedPages TYPO3 pages whose translated content records are fetched too
     * @return Collection<int, mixed> Fetched content records which are not expanded and the target records of their shortcuts
     */
    public function prefetch( iterable $t3Pages, iterable $translatedPages ): Collection
    {
        $pids = $translatedPids = [];

        foreach( $t3Pages as $t3Page ) {
            $pids[$this->sourcePage( $t3Page )] = true;
        }

        foreach( $translatedPages as $t3Page ) {
            $translatedPids[$this->sourcePage( $t3Page )] = true;
        }

        $this->contents = array_fill_keys( array_keys( $pids ), Collection::make() );
        $this->translations = array_fill_keys( array_keys( $translatedPids ), Collection::make() );

        $records = $pids ? ( $this->content )( array_keys( $pids ) ) : Collection::make();
        $translated = $translatedPids ? ( $this->translated )( array_keys( $translatedPids ) ) : Collection::make();

        foreach( $records->groupBy( fn( $record ) => (int) ( $record->pid ?? 0 ) ) as $pid => $list ) {
            $this->contents[$pid] = $list->values();
        }

        foreach( $translated->groupBy( fn( $record ) => (int) ( $record->pid ?? 0 ) ) as $pid => $list ) {
            $this->translations[$pid] = $list->values();
        }

        // the targets of the shortcuts are fetched for all pages at once, so expand() doesn't fetch them per page
        $all = $records->concat( $translated );

        return $all->concat( $this->fetch( $all ) );
    }


    /**
     * Returns the UIDs of the content records shown by the content shortcuts of the TYPO3 pages including the ones of nested shortcuts.
     *
     * @param array<int, int> $pids UIDs of the TYPO3 pages
     * @return array<int, list<int>> UIDs of the content records by page UID, only for pages with content shortcuts
     */
    public function shortcutTargets( array $pids ): array
    {
        if( $this->shortcutTargets === null )
        {
            $this->shortcutTargets = [];

            foreach( $this->shortcuts as $record )
            {
                $this->shortcutTargets[$uid = (int) ( $record->uid ?? 0 )] = $this->targets( $record );
                $this->shortcutPages[(int) ( $record->pid ?? 0 )][] = $uid;
            }
        }

        $result = [];

        foreach( $pids as $pid )
        {
            if( !isset( $this->shortcutPages[$pid] ) ) {
                continue;
            }

            $uids = [];
            $stack = $this->shortcutPages[$pid];

            // the shortcuts of the page aren't shown records themselves but the targets of nested shortcuts are
            while( ( $uid = array_pop( $stack ) ) !== null )
            {
                foreach( $this->shortcutTargets[$uid] ?? [] as $target )
                {
                    if( !isset( $uids[$target] ) )
                    {
                        $uids[$target] = true;
                        $stack[] = $target;
                    }
                }
            }

            $result[$pid] = array_keys( $uids );
        }

        return $result;
    }


    /**
     * Tests if the content records of the TYPO3 page in default language are already fetched.
     */
    public function prefetched( object $t3Page ): bool
    {
        $uid = $this->sourcePage( $t3Page );

        return $uid === $this->lastPage || isset( $this->contents[$uid] );
    }


    /**
     * Returns the expanded content records of a TYPO3 page.
     *
     * For translations, the translated records and the records for all languages are returned.
     *
     * @param int|null $langUid TYPO3 language UID of the translation or NULL for the default language
     * @return Collection<int, mixed>
     */
    public function page( object $t3Page, ?int $langUid = null ): Collection
    {
        $contentPageUid = $this->sourcePage( $t3Page );
        $this->current = $t3Page;

        // the translations of a page use the records for all languages of the page fetched before
        if( $this->lastPage !== $contentPageUid )
        {
            $this->lastRecords = $this->contents[$contentPageUid] ?? ( $this->content )( [$contentPageUid] );
            $this->lastTranslated = isset( $this->translations[$contentPageUid] ) ? $this->group( $this->translations[$contentPageUid] ) : null;
            $this->lastPage = $contentPageUid;
        }

        $records = $this->lastRecords;

        if( $langUid !== null )
        {
            // the translated records of all languages are fetched once when the first translation is imported
            $this->lastTranslated ??= $this->group( ( $this->translated )( [$contentPageUid] ) );

            $records = $this->overlay( $records, $this->lastTranslated->get( $langUid ) ?? Collection::make(), $langUid );
        }

        // records shown on other pages are marked as shared by expand() because their page is a content source
        return $this->expand( $this->layouts->sort( $records, $t3Page ), $langUid );
    }


    /**
     * Returns the content records of a translation like TYPO3 shows them depending on the fallback type of the language.
     *
     * "strict" (default): the translations of the records in default language are shown in their place,
     * untranslated records are skipped and records without translation source ("floating" records) are
     * shown too. "fallback": the translations are shown in place of their source records, untranslated
     * records are shown in default language and floating records aren't shown. "free": only the records
     * of the language are shown in their own order. The records for all languages are shown in all modes.
     *
     * @param Collection<int, mixed> $records Content records in default language and for all languages
     * @param Collection<int, mixed> $translated Content records of the language
     * @param int $langUid TYPO3 language UID
     * @return Collection<int, mixed> Content records ordered by their sorting
     */
    protected function overlay( Collection $records, Collection $translated, int $langUid ): Collection
    {
        $all = $records->filter( fn( $record ) => (int) ( $record->sys_language_uid ?? 0 ) === -1 );
        $mode = $this->mode( $langUid );
        $sort = fn( Collection $list ) => $list->sortBy( fn( $record ) => [(int) ( $record->sorting ?? 0 ), (int) ( $record->uid ?? 0 )] )->values();

        if( $mode === 'free' ) {
            return $sort( $translated->concat( $all ) );
        }

        $overlays = $translated->filter( fn( $record ) => (int) ( $record->l18n_parent ?? 0 ) > 0 )->keyBy( fn( $record ) => (int) $record->l18n_parent );
        // TYPO3 shows floating records only for "strict" (overlays with floating records), not for "fallback" (mixed overlays)
        $list = $mode === 'strict' ? $translated->filter( fn( $record ) => (int) ( $record->l18n_parent ?? 0 ) === 0 )->values()->all() : [];

        foreach( $records as $record )
        {
            if( (int) ( $record->sys_language_uid ?? 0 ) === -1 ) {
                $list[] = $record;
            } elseif( $overlay = $overlays->get( (int) ( $record->uid ?? 0 ) ) ) {
                // translations are shown at the position of their source record
                $list[] = (object) ( ['colPos' => $record->colPos ?? 0, 'sorting' => $record->sorting ?? 0] + get_object_vars( $overlay ) );
            } elseif( $mode === 'fallback' ) {
                $list[] = $record;
            }
        }

        return $sort( Collection::make( $list ) );
    }


    /**
     * Replaces TYPO3 content shortcuts with their canonical target records and
     * marks every referenced source record for Pagible shared-element storage.
     *
     * @param Collection<int, mixed> $records
     * @return Collection<int, mixed>
     */
    protected function expand( Collection $records, ?int $langUid = null ): Collection
    {
        if( $this->referenced === null || $this->sourcePages === null )
        {
            $this->referenced = $this->sourcePages = [];

            foreach( $this->shortcuts as $record )
            {
                foreach( $this->targets( $record ) as $uid ) {
                    $this->referenced[$uid] = true;
                }
            }

            foreach( $this->pages as $page )
            {
                if( (int) ( $page->content_from_pid ?? 0 ) > 0 ) {
                    $this->sourcePages[$this->sourcePage( $page )] = true;
                }
            }
        }

        $this->fetch( $records );

        return $records->flatMap( fn( $record ) => $this->expandRecord( $record, langUid: $langUid ) )->values();
    }


    /**
     * Expands one TYPO3 shortcut recursively while retaining its placement.
     *
     * @param array<int, bool> $seen
     * @return list<object>
     */
    protected function expandRecord( object $record, ?object $placement = null, ?string $kind = null, array $seen = [], ?int $langUid = null ): array
    {
        $uid = (int) ( $record->uid ?? 0 );
        // translations are referenced by the UIDs of their source records
        $canonical = (int) ( $record->l18n_parent ?? 0 ) ?: $uid;

        if( (string) ( $record->CType ?? '' ) === 'shortcut' )
        {
            if( isset( $seen[$uid] ) ) {
                return [];
            }

            $seen[$uid] = true;
            $placement ??= $record;
            $kind ??= (string) ( $record->_pagible_shared ?? 'reference' );
            $result = [];

            foreach( $this->targets( $record ) as $targetUid )
            {
                if( !( $target = $this->target( $targetUid ) ) || $targetUid === $uid || !( $target = $this->translation( $target, $langUid ) ) ) {
                    continue;
                }

                array_push( $result, ...$this->expandRecord( $target, $placement, $kind, $seen, $langUid ) );
            }

            return $result;
        }

        $kind ??= (string) ( $record->_pagible_shared ?? '' );

        if( $kind === '' && ( isset( $this->referenced[$canonical] ) || isset( $this->sourcePages[(int) ( $record->pid ?? 0 )] ) ) ) {
            $kind = 'reference';
        }

        if( !$placement && $kind === '' ) {
            return [$record];
        }

        $data = get_object_vars( $record );

        if( $placement )
        {
            $placement = get_object_vars( $placement );
            $data['colPos'] = (int) ( $placement['colPos'] ?? $data['colPos'] ?? 0 );
            $data['sorting'] = (int) ( $placement['sorting'] ?? $data['sorting'] ?? 0 );
        }

        if( $kind !== '' )
        {
            $data['_pagible_shared'] = $kind;
            $data['_pagible_shared_page'] = (int) ( $record->_pagible_shared_page ?? $record->pid ?? 0 );
            $data['_pagible_shared_uid'] = (int) ( $record->_pagible_shared_uid ?? $uid );
        }

        return [(object) $data];
    }



    /**
     * Fetches the targets of the shortcuts and their nested shortcuts at once per level.
     *
     * The targets fetched before are kept as the newest ones, so they are discarded last.
     *
     * @param Collection<int, mixed> $records Content records
     * @return Collection<int, mixed> Existing target records of the shortcuts including the ones of nested shortcuts
     */
    protected function fetch( Collection $records ): Collection
    {
        $found = [];

        while( $records->isNotEmpty() )
        {
            $uids = $records->filter( fn( $record ) => (string) ( $record->CType ?? '' ) === 'shortcut' )
                ->flatMap( fn( $record ) => $this->targets( $record ) )
                ->reject( fn( $uid ) => array_key_exists( $uid, $found ) )
                ->unique()->values()->all();

            if( !$uids ) {
                break;
            }

            $missing = array_values( array_filter( $uids, fn( $uid ) => !array_key_exists( $uid, $this->targetRecords ) ) );
            $fetched = $missing ? ( $this->records )( $missing ) : Collection::make();
            $level = [];

            foreach( $uids as $uid )
            {
                $level[$uid] = $found[$uid] = array_key_exists( $uid, $this->targetRecords ) ? $this->targetRecords[$uid] : $fetched->get( $uid );
                unset( $this->targetRecords[$uid] );
                $this->targetRecords[$uid] = $level[$uid];
            }

            $this->targetRecords = Database::limit( $this->targetRecords, static::CACHE_SIZE, count( $uids ) );
            $records = Collection::make( $level )->filter()->values();
        }

        return Collection::make( $found )->filter()->values();
    }


    /**
     * Returns the content record in default language referenced by a shortcut.
     */
    protected function target( int $uid ): ?object
    {
        if( !array_key_exists( $uid, $this->targetRecords ) ) {
            $this->targetRecords[$uid] = ( $this->records )( [$uid] )->get( $uid );
            $this->targetRecords = Database::limit( $this->targetRecords, static::CACHE_SIZE );
        }

        return $this->targetRecords[$uid] ?? null;
    }


    /**
     * Returns the translation of the content record shown by a shortcut in the language.
     *
     * Records for all languages are shown in every language. Untranslated records are shown in default
     * language for the "fallback" and "free" modes and skipped for "strict" like TYPO3 does.
     *
     * @param object $record Content record in default language or for all languages
     * @param int|null $langUid TYPO3 language UID or NULL for the default language
     * @return object|null Translated record, the record itself or NULL if it isn't shown
     */
    protected function translation( object $record, ?int $langUid ): ?object
    {
        if( $langUid === null || !$this->overlays || (int) ( $record->sys_language_uid ?? 0 ) !== 0 ) {
            return $record;
        }

        $uid = (int) ( $record->uid ?? 0 );

        if( !array_key_exists( $key = $langUid . ':' . $uid, $this->targetOverlays ) )
        {
            $this->targetOverlays[$key] = ( $this->overlays )( [$uid], $langUid )->get( $uid );
            $this->targetOverlays = Database::limit( $this->targetOverlays, static::CACHE_SIZE );
        }

        if( $overlay = $this->targetOverlays[$key] ) {
            return $overlay;
        }

        return $this->mode( $langUid ) === 'strict' ? null : $record;
    }


    /**
     * Returns the TYPO3 fallback type of the language in the site of the current page.
     *
     * @return string "strict", "fallback" or "free"
     */
    protected function mode( int $langUid ): string
    {
        return $this->mode ? ( $this->mode )( $langUid, $this->current ) : 'strict';
    }


    /**
     * Groups the translated content records by their language UID.
     *
     * @param Collection<int, mixed> $records Translated content records
     * @return Collection<int|string, Collection<int, mixed>> Records grouped by language UID
     */
    protected function group( Collection $records ): Collection
    {
        return $records->groupBy( fn( $record ) => (int) ( $record->sys_language_uid ?? 0 ) );
    }


    /**
     * Returns the UID of the page whose content is shown for the TYPO3 page.
     *
     * Like TYPO3, only the "content_from_pid" of the page itself is used, not the one of the source page,
     * but shortcuts are followed if the source page is a shortcut.
     */
    public function sourcePage( object $t3Page ): int
    {
        $uid = (int) ( $t3Page->uid ?? 0 );

        if( ( $sourceUid = (int) ( $t3Page->content_from_pid ?? 0 ) ) <= 0 || $sourceUid === $uid ) {
            return $uid;
        }

        $seen = [];

        while( ( $source = $this->pages->get( $sourceUid ) ) && (int) ( $source->doktype ?? 1 ) === 4 && !isset( $seen[$sourceUid] ) )
        {
            $seen[$sourceUid] = true;
            $target = match( (int) ( $source->shortcut_mode ?? 0 ) ) {
                0 => (int) ( $source->shortcut ?? 0 ),
                1, 2 => (int) ( $this->pages->first( fn( $page ) => (int) ( $page->pid ?? 0 ) === ( (int) ( $source->shortcut ?? 0 ) ?: $sourceUid )
                    && empty( $page->hidden ) && (int) ( $page->doktype ?? 1 ) < 199 && (int) ( $page->doktype ?? 1 ) !== Tree::BE_SECTION
                    && Tree::restricted( $page ) === null )->uid ?? 0 ),
                3 => (int) ( $source->pid ?? 0 ),
                default => 0,
            };

            if( $target <= 0 ) {
                break;
            }

            $sourceUid = $target;
        }

        return $sourceUid;
    }


    /**
     * Returns canonical tt_content UIDs referenced by a TYPO3 shortcut.
     *
     * @return list<int>
     */
    protected function targets( object $record ): array
    {
        $uids = [];

        foreach( preg_split( '/\s*,\s*/', trim( (string) ( $record->records ?? '' ) ) ) ?: [] as $value )
        {
            if( preg_match( '/^(?:tt_content[_:]?)?(\d+)$/', $value, $match ) ) {
                $uids[] = (int) $match[1];
            }
        }

        return array_values( array_unique( $uids ) );
    }
}

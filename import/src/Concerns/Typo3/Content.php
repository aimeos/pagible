<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Typo3;

use Aimeos\Cms\Import\Database;
use Aimeos\Cms\Import\SharedElements;
use Aimeos\Cms\Import\Typo3;
use Illuminate\Support\Collection;
use Aimeos\Cms\DB as CmsDB;


/**
 * Converts the TYPO3 content records of the pages into content and shared elements.
 */
trait Content
{
    /** TYPO3 page in default language whose content is imported, used for links to the current page */
    protected ?object $currentPage = null;

    /** @var array<string, array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>, elementIds: array<int, string>}> References to the shared elements by TYPO3 content and language */
    protected array $shared = [];


    /**
     * Keeps only the last references to the shared elements before the batch is committed.
     *
     * The shared elements of other TYPO3 content are found by their name again when they are used.
     */
    #[\Override]
    protected function committing(): void
    {
        parent::committing();
        $this->shared = Database::limit( $this->shared, static::PREFETCH_SIZE );
    }


    /**
     * Builds content elements array from TYPO3 tt_content records.
     *
     * @param Collection<int, mixed> $records
     * @param Typo3\Content|null $typo3 Converter of the content elements
     * @param string $lang Language code of the content elements
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>, elementIds: array<int, string>}
     */
    protected function buildContent( Collection $records, ?Typo3\Content $typo3, string $lang ): array
    {
        $elements = $fileIds = $elementIds = $anchors = [];

        // the file references of all content elements of the page and the files of previous imports are fetched at once
        if( $typo3 )
        {
            $typo3->prefetch( $records );
            $this->prefetchFiles( $this->files->paths( $typo3->references(), $typo3->texts( $records ) ), false );
        }

        foreach( $this->nest( $records ) as $record )
        {
            $result = $this->attempt( 'content element [' . ( $record->uid ?? '?' ) . ']', fn() => $this->convert( $record, $typo3, $lang ) );

            if( $result )
            {
                // links to the TYPO3 content anchors like "#c123" point to the first element of the record, translations use the anchor of their source
                $anchor = Typo3\Urls::anchor( (int) ( $record->l18n_parent ?? 0 ) ?: (int) ( $record->uid ?? 0 ) );

                if( isset( $result['elements'][0] ) && !isset( $anchors[$anchor] ) ) {
                    $result['elements'][0]['id'] = $anchors[$anchor] = $anchor;
                }

                $group = $this->groups[$this->column( $record, $records )] ?? null;
                $elements = array_merge( $elements, $group ? array_map( fn( $element ) => array_replace( $element, ['group' => $group] ), $result['elements'] ) : $result['elements'] );
                $fileIds = array_merge( $fileIds, $result['fileIds'] );
                $elementIds = array_merge( $elementIds, $result['elementIds'] );
            }
        }

        return [
            'elements' => $elements,
            'fileIds' => array_values( array_unique( $fileIds ) ),
            'elementIds' => array_values( array_unique( $elementIds ) ),
        ];
    }


    /**
     * Orders the content records of containers directly after their container, e.g. of EXT:container or EXT:gridelements.
     *
     * The children are ordered by the column of the container and their sorting. Children whose
     * container isn't shown, e.g. because it's hidden, aren't shown by TYPO3 either.
     * Translated children reference the container in default language.
     *
     * @param Collection<int, mixed> $records Content records in their order
     * @return array<int, object> Content records in their order with the children after their container
     */
    protected function nest( Collection $records ): array
    {
        $uids = [];
        $children = $list = [];

        foreach( $records as $record )
        {
            $uids[(int) ( $record->uid ?? 0 )] = (int) ( $record->uid ?? 0 );

            if( $source = (int) ( $record->l18n_parent ?? 0 ) ) {
                $uids[$source] ??= (int) ( $record->uid ?? 0 );
            }
        }

        foreach( $records as $record )
        {
            if( !( $parent = $this->container( $record ) ) ) {
                $list[] = $record;
            } elseif( isset( $uids[$parent] ) && $uids[$parent] !== (int) ( $record->uid ?? 0 ) ) {
                $children[$uids[$parent]][] = $record;
            }
        }

        foreach( $children as $parent => $list2 )
        {
            usort( $list2, fn( $a, $b ) => [$this->containerColumn( $a ), (int) ( $a->sorting ?? 0 ), (int) ( $a->uid ?? 0 )]
                <=> [$this->containerColumn( $b ), (int) ( $b->sorting ?? 0 ), (int) ( $b->uid ?? 0 )] );
            $children[$parent] = $list2;
        }

        $result = [];
        $seen = [];
        $add = function( \stdClass $record ) use ( &$add, &$result, &$seen, $children ) {
            if( isset( $seen[$uid = (int) ( $record->uid ?? 0 )] ) ) {
                return;
            }

            $seen[$uid] = true;
            $result[] = $record;

            // containers have no content of their own and aren't reported as unknown content types
            if( !empty( $children[$uid] ) ) {
                $record->_pagible_container = true;
            }

            foreach( $children[$uid] ?? [] as $child ) {
                $add( $child );
            }
        };

        foreach( $list as $record ) {
            $add( $record );
        }

        return $result;
    }


    /**
     * Returns the column of the content record inside its container.
     */
    protected function containerColumn( object $record ): int
    {
        return (int) ( !empty( $record->tx_gridelements_container ) && empty( $record->tx_container_parent )
            ? $record->tx_gridelements_columns ?? 0 : $record->colPos ?? 0 );
    }


    /**
     * Returns the UID of the container of the content record or 0 if it's not inside a container.
     */
    protected function container( object $record ): int
    {
        return (int) ( ( $record->tx_container_parent ?? 0 ) ?: ( $record->tx_gridelements_container ?? 0 ) );
    }


    /**
     * Returns the TYPO3 column of the content record, the one of the outermost container for content inside containers.
     *
     * @param Collection<int, mixed> $records Content records of the page
     */
    protected function column( object $record, Collection $records ): int
    {
        if( !$this->groups ) {
            return 0;
        }

        $uids = $records->keyBy( fn( $item ) => (int) ( $item->uid ?? 0 ) );

        for( $depth = 0; $depth < 20 && ( $parent = $uids->get( $this->container( $record ) ) ); $depth++ ) {
            $record = $parent;
        }

        return (int) ( $record->colPos ?? 0 );
    }


    /**
     * Returns the rewriter for the files and links in the HTML of the content elements.
     *
     * Links to pages in another language, e.g. "t3://page?uid=1&_language=2", point to the page in that language.
     *
     * @param array{uid: int, source: string, chain?: array<int, int>}|null $translation TYPO3 language of translated content or NULL for the source content
     */
    protected function rewriter( ?array $translation = null ): Typo3\Html
    {
        return new Typo3\Html(
            fn( string $url ) => $this->attempt( "file {$url}", fn() => $this->files->html( $url ) ),
            fn( int $uid, string $fragment, ?int $langUid = null ) => ( $uid = $uid ?: (int) ( $this->currentPage->uid ?? 0 ) ) > 0
                ? $this->urls->page( $uid, $fragment, $this->linkTranslation( $uid, $langUid, $translation ) ) : null,
            fn( int $uid ) => $this->attempt( "file [{$uid}]", fn() => ( $sysFile = $this->reader->filesByUid( [$uid] )->get( $uid ) ) ? $this->files->file( $sysFile ) : null ),
            $this->files->pattern(),
            fn( int $storage, string $identifier ) => $this->attempt( "file {$storage}:{$identifier}", fn() => $this->files->file(
                $this->reader->filesByIdentifier( [$identifier] )->first( fn( $file ) => (int) ( $file->storage ?? 0 ) === $storage )
                    ?? (object) ['storage' => $storage, 'identifier' => $identifier, 'name' => basename( $identifier )]
            ) )
        );
    }


    /**
     * Returns the TYPO3 language a link to the page points to.
     *
     * @param int|null $langUid Language UID of the link, e.g. from "_language=2", or NULL for the language of the content
     * @param array{uid: int, source: string, chain?: array<int, int>}|null $translation TYPO3 language of the content
     * @return array{uid: int, source: string, chain?: array<int, int>}|null TYPO3 language of the link target or NULL for the default language
     */
    protected function linkTranslation( int $uid, ?int $langUid, ?array $translation ): ?array
    {
        if( $langUid === null || $langUid === ( $translation['uid'] ?? 0 ) ) {
            return $translation;
        }

        $source = $translation['source'] ?? '';

        if( $langUid === 0 ) {
            return $source !== '' ? ['uid' => 0, 'source' => $source] : null;
        }

        $root = ( $page = $this->pagesById->get( $uid ) ) ? $this->siteRoot( $page ) : null;

        return ['uid' => $langUid, 'source' => $source, 'chain' => $this->site->chain( $langUid, $root )];
    }


    /**
     * Converts the tt_content record and stores its elements as shared elements if it's referenced by other pages.
     *
     * @param Typo3\Content|null $typo3 Converter of the content element
     * @param string $lang Language code of the content element
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>, elementIds: array<int, string>}|null
     */
    protected function convert( object $record, ?Typo3\Content $typo3, string $lang ): ?array
    {
        // content shown on several pages is converted and stored only once per language
        $key = isset( $record->_pagible_shared ) ? $this->sharedElementName( $record, 0 ) . '/' . $lang : null;

        if( $key && isset( $this->shared[$key] ) ) {
            return $this->shared[$key];
        }

        if( !$typo3 || !( $result = $typo3->convert( $record ) ) ) {
            return null;
        }

        if( !$key ) {
            return ['elements' => $result['elements'], 'fileIds' => $result['fileIds'] ?? [], 'elementIds' => []];
        }

        // the shared elements of the record are stored together or not at all
        $elementIds = CmsDB::connection()->transaction( fn() => array_map(
            fn( $position, $element ) => SharedElements::save( $this->sharedElementName( $record, $position ), $lang, $element, $result['fileIds'] ?? [], $this->editor ),
            array_keys( $result['elements'] ), $result['elements']
        ) );
        $references = array_map( fn( $id ) => ['type' => 'reference', 'refid' => $id, 'group' => 'main'], $elementIds );

        return $this->shared[$key] = ['elements' => $references, 'fileIds' => [], 'elementIds' => $elementIds];
    }


    /**
     * Returns the persistent, source-specific name used to find shared elements on re-import.
     */
    protected function sharedElementName( object $record, int $position ): string
    {
        return sprintf(
            'TYPO3 element %s/%d/%d/%d',
            substr( sha1( $this->connection ), 0, 12 ),
            (int) ( $record->_pagible_shared_page ?? $record->pid ?? 0 ),
            (int) ( $record->_pagible_shared_uid ?? $record->uid ?? 0 ),
            $position
        );
    }
}

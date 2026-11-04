<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Aimeos\Cms\Utils;
use Illuminate\Support\Collection;


/**
 * Converts TYPO3 tt_content records into Pagible content elements.
 *
 * Supports the core content types and the accordion and carousel of the Bootstrap Package.
 */
class Typo3
{
    /**
     * Initializes the converter.
     *
     * @param \Closure(object): ?string $file Imports the file of the sys_file_reference record and returns the file ID
     * @param Typo3Html $rewriter Rewrites the files and links in the HTML of the content elements
     * @param \Closure(array<int, int>): Collection<int|string, mixed> $fileRefs Fetches the sys_file_reference records of the content elements grouped by content UID
     * @param \Closure(array<int, int>): Collection<int|string, mixed> $accordions Fetches the accordion items of the content elements grouped by content UID
     * @param \Closure(array<int, int>): Collection<int|string, mixed> $carousels Fetches the carousel items of the content elements grouped by content UID
     * @param \Closure(array<int, int>): Collection<int|string, mixed> $carouselRefs Fetches the sys_file_reference records of the carousel items grouped by item UID
     */
    public function __construct(
        protected \Closure $file,
        protected Typo3Html $rewriter,
        protected \Closure $fileRefs,
        protected \Closure $accordions,
        protected \Closure $carousels,
        protected \Closure $carouselRefs
    ) {
        $this->refs = $this->accordionItems = $this->carouselItems = $this->carouselFileRefs = Collection::make();
    }


    /** @var Collection<int|string, mixed> sys_file_reference records of the prefetched content elements grouped by content UID */
    protected Collection $refs;

    /** @var Collection<int|string, mixed> Accordion items of the prefetched content elements grouped by content UID */
    protected Collection $accordionItems;

    /** @var Collection<int|string, mixed> Carousel items of the prefetched content elements grouped by content UID */
    protected Collection $carouselItems;

    /** @var Collection<int|string, mixed> sys_file_reference records of the prefetched carousel items grouped by item UID */
    protected Collection $carouselFileRefs;

    /** @var array<int, bool> UIDs of the prefetched content elements */
    protected array $prefetched = [];

    /** @var array<int, bool> UIDs of the content elements passed to prefetch() last */
    protected array $current = [];

    /** @var array<int, bool> UIDs of the content elements of the upcoming pages passed to ahead() last */
    protected array $ahead = [];


    /**
     * Fetches the file references and the accordion and carousel items of the content records at once.
     *
     * Data of records prefetched before, e.g. the ones for all languages or the ones of the
     * upcoming pages, isn't fetched again. The data of the records is kept until ahead() is
     * called again.
     *
     * @param iterable<object> $records TYPO3 content records
     */
    public function prefetch( iterable $records ): void
    {
        $this->current = $this->load( $records );
    }


    /**
     * Fetches the file references and the accordion and carousel items of the content records of the upcoming pages at once.
     *
     * Only the data of these records and of the records passed to prefetch() last is kept, so prefetch() doesn't fetch it per page.
     *
     * @param iterable<object> $records TYPO3 content records of the upcoming pages
     * @return Collection<int, mixed> sys_file_reference records of the content records including the ones of their carousel items
     */
    public function ahead( iterable $records ): Collection
    {
        $this->ahead = $this->load( $records );
        $this->prune( array_keys( $this->ahead + $this->current ) );

        return $this->referencesOf( $this->ahead );
    }


    /**
     * Returns the file references of the prefetched content records including the ones of their carousel items.
     *
     * @return Collection<int, mixed> sys_file_reference records with their sys_file record in the "sys_file" property
     */
    public function references(): Collection
    {
        return $this->referencesOf( $this->current );
    }


    /**
     * Returns the HTML texts of the content records including the ones of their prefetched accordion items.
     *
     * @param iterable<object> $records Content records passed to prefetch() or ahead() before
     * @return array<int, string> HTML texts
     */
    public function texts( iterable $records ): array
    {
        $texts = [];

        foreach( $records as $record )
        {
            $texts[] = (string) ( $record->bodytext ?? '' );

            foreach( $this->accordionItems->get( (int) ( $record->uid ?? 0 ), [] ) as $item ) {
                $texts[] = (string) ( $item->bodytext ?? '' );
            }
        }

        return $texts;
    }


    /**
     * Returns a copy of the converter which uses the given rewriter for the HTML of the content elements.
     */
    public function withRewriter( Typo3Html $rewriter ): static
    {
        $copy = clone $this;
        $copy->rewriter = $rewriter;

        return $copy;
    }


    /**
     * Fetches the file references and the accordion and carousel items of the records which aren't prefetched yet.
     *
     * @param iterable<object> $records TYPO3 content records
     * @return array<int, bool> UIDs of the content records
     */
    protected function load( iterable $records ): array
    {
        $uids = $missing = $types = [];

        foreach( $records as $record )
        {
            $uids[$uid = (int) ( $record->uid ?? 0 )] = true;

            if( !isset( $this->prefetched[$uid] ) )
            {
                $this->prefetched[$uid] = true;
                $missing[] = $uid;
                $types[(string) ( $record->CType ?? '' )][] = $uid;
            }
        }

        // the data of the content records passed before is only discarded by prune()
        if( $missing === [] ) {
            return $uids;
        }

        $carousels = isset( $types['carousel'] ) ? ( $this->carousels )( $types['carousel'] ) : Collection::make();

        $this->refs = $this->refs->union( ( $this->fileRefs )( $missing ) );
        $this->accordionItems = $this->accordionItems->union( isset( $types['accordion'] ) ? ( $this->accordions )( $types['accordion'] ) : [] );
        $this->carouselItems = $this->carouselItems->union( $carousels );
        $this->carouselFileRefs = $this->carouselFileRefs->union( ( $new = $this->itemUids( $carousels ) ) ? ( $this->carouselRefs )( $new ) : [] );

        return $uids;
    }


    /**
     * Discards the data of the content records which aren't kept.
     *
     * @param array<int, int> $keep UIDs of the content records whose data is kept
     */
    protected function prune( array $keep ): void
    {
        $this->refs = $this->refs->only( $keep );
        $this->accordionItems = $this->accordionItems->only( $keep );
        $this->carouselItems = $this->carouselItems->only( $keep );
        $this->carouselFileRefs = $this->carouselFileRefs->only( $this->itemUids( $this->carouselItems ) );
        $this->prefetched = array_fill_keys( $keep, true );
    }


    /**
     * Returns the file references of the content records including the ones of their carousel items.
     *
     * @param array<int, bool> $uids UIDs of the prefetched content records
     * @return Collection<int, mixed> sys_file_reference records with their sys_file record in the "sys_file" property
     */
    protected function referencesOf( array $uids ): Collection
    {
        $keys = array_keys( $uids );

        return $this->refs->only( $keys )->flatten( 1 )
            ->concat( $this->carouselFileRefs->only( $this->itemUids( $this->carouselItems->only( $keys ) ) )->flatten( 1 ) );
    }


    /**
     * Returns the UIDs of the accordion or carousel items.
     *
     * @param Collection<int|string, mixed> $items Items grouped by content UID
     * @return array<int, int> Item UIDs
     */
    protected function itemUids( Collection $items ): array
    {
        return $items->flatten( 1 )->map( fn( $item ) => (int) ( $item->uid ?? 0 ) )->all();
    }


    /**
     * Converts a TYPO3 tt_content record into Pagible content elements.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>}|null
     */
    public function convert( object $record ): ?array
    {
        return match( (string) ( $record->CType ?? '' ) ) {
            'header' => $this->header( $record ),
            'textpic', 'textmedia' => $this->textpic( $record ),
            'image' => $this->image( $record ),
            'html' => $this->html( $record ),
            'accordion' => $this->accordion( $record ),
            'carousel' => $this->carousel( $record ),
            default => $this->default( $record ),
        };
    }


    /**
     * Converts a Bootstrap Package accordion into a questions element.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function accordion( object $record ): ?array
    {
        $items = [];
        $fileIds = [];

        foreach( $this->accordionItems->get( (int) ( $record->uid ?? 0 ), Collection::make() ) as $child )
        {
            if( empty( $child->header ) || empty( $child->bodytext ) ) {
                continue;
            }

            $result = $this->rewriter->rewrite( (string) $child->bodytext );
            $items[] = ['title' => $child->header, 'text' => Markdown::fromHtml( $result['html'] )];
            $fileIds = array_merge( $fileIds, $result['fileIds'] );
        }

        if( empty( $items ) && !empty( $record->bodytext ) )
        {
            $result = $this->rewriter->rewrite( (string) $record->bodytext );
            $items[] = ['title' => ( $record->header ?? '' ) ?: 'Item', 'text' => Markdown::fromHtml( $result['html'] )];
            $fileIds = array_merge( $fileIds, $result['fileIds'] );
        }

        if( empty( $items ) ) {
            return null;
        }

        $element = Elements::create( 'questions', ['title' => (string) ( $record->header ?? '' ), 'items' => $items] );
        return ['elements' => [$element], 'fileIds' => array_values( array_unique( $fileIds ) )];
    }


    /**
     * Converts a Bootstrap Package carousel into a slideshow or an image element if it has only one image.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function carousel( object $record ): ?array
    {
        $files = [];
        $fileIds = [];

        foreach( $this->carouselItems->get( (int) ( $record->uid ?? 0 ), Collection::make() ) as $child )
        {
            foreach( $this->carouselFileRefs->get( (int) ( $child->uid ?? 0 ), Collection::make() ) as $ref )
            {
                if( $id = ( $this->file )( $ref ) )
                {
                    $files[] = Elements::file( $id );
                    $fileIds[] = $id;
                }
            }
        }

        if( $files === [] ) {
            return $this->default( $record );
        }

        $element = count( $files ) > 1
            ? Elements::create( 'slideshow', ['title' => (string) ( $record->header ?? '' ), 'files' => $files] )
            : Elements::create( 'image', ['file' => $files[0]] );

        return ['elements' => [$element], 'fileIds' => array_values( array_unique( $fileIds ) )];
    }


    /**
     * Converts a content element of another type with bodytext into an html element.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function default( object $record ): ?array
    {
        $elements = $this->heading( $record );
        $fileIds = [];

        if( !empty( $text = trim( (string) ( $record->bodytext ?? '' ) ) ) )
        {
            $result = $this->rewriter->rewrite( $text );
            $elements[] = Elements::create( 'html', ['text' => Utils::html( $result['html'] )] );
            $fileIds = $result['fileIds'];
        }

        if( empty( $elements ) ) {
            return null;
        }

        return ['elements' => $elements, 'fileIds' => array_values( array_unique( $fileIds ) )];
    }


    /**
     * Imports the first file referenced by the tt_content record.
     *
     * @return string|null File ID or NULL if the content element has no file or it can't be imported
     */
    protected function fileOf( object $record ): ?string
    {
        $uid = (int) ( $record->uid ?? 0 );
        $refs = isset( $this->prefetched[$uid] ) ? $this->refs->get( $uid ) : ( $this->fileRefs )( [$uid] )->get( $uid );

        if( !$refs || $refs->isEmpty() ) {
            return null;
        }

        return ( $this->file )( $refs->first() );
    }


    /**
     * Converts a header content element into a heading element.
     *
     * @return array{elements: array<int, array<string, mixed>>}|null
     */
    protected function header( object $record ): ?array
    {
        if( empty( $record->header ) ) {
            return null;
        }

        return ['elements' => [Elements::create( 'heading', [
            'level' => $this->headerLevel( $record->header_layout ?? null ),
            'title' => $record->header,
        ] )]];
    }


    /**
     * Maps the TYPO3 header_layout to the heading level.
     */
    protected function headerLevel( mixed $layout ): int
    {
        return is_scalar( $layout ) && in_array( (string) $layout, ['1', '2', '3', '4', '5'], true ) ? (int) $layout : 2;
    }


    /**
     * Returns the heading element of a content record unless its header is hidden.
     *
     * @return list<array<string, mixed>>
     */
    protected function heading( object $record ): array
    {
        if( empty( $record->header ) || ( $record->header_layout ?? '0' ) === '100' ) {
            return [];
        }

        return [Elements::create( 'heading', [
            'level' => $this->headerLevel( $record->header_layout ?? null ),
            'title' => $record->header,
        ] )];
    }


    /**
     * Converts an html content element into a Pagible html element.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function html( object $record ): ?array
    {
        if( empty( $record->bodytext ) ) {
            return null;
        }

        $result = $this->rewriter->rewrite( (string) $record->bodytext );

        return ['elements' => [Elements::create( 'html', ['text' => Utils::html( $result['html'] )] )], 'fileIds' => $result['fileIds']];
    }


    /**
     * Converts an image content element into a Pagible image element.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function image( object $record ): ?array
    {
        if( !( $fileId = $this->fileOf( $record ) ) ) {
            return null;
        }

        return ['elements' => [...$this->heading( $record ), Elements::image( $fileId )], 'fileIds' => [$fileId]];
    }


    /**
     * Maps the TYPO3 imageorient to the Pagible position.
     */
    protected function imagePosition( int $orient ): string
    {
        return match( $orient ) {
            17, 18, 126 => 'start',
            25, 26, 125 => 'end',
            default => 'auto',
        };
    }


    /**
     * Converts a textpic/textmedia content element into an image-text element.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function textpic( object $record ): ?array
    {
        $elements = $this->heading( $record );
        $fileId = $this->fileOf( $record );
        $text = trim( (string) ( $record->bodytext ?? '' ) );
        $fileIds = [];

        if( $text !== '' )
        {
            $result = $this->rewriter->rewrite( $text );
            $text = $result['html'];
            $fileIds = $result['fileIds'];
        }

        if( $fileId && !empty( $text ) )
        {
            $elements[] = Elements::create( 'image-text', [
                'text' => $text,
                'file' => Elements::file( $fileId ),
                'position' => $this->imagePosition( (int) ( $record->imageorient ?? 0 ) ),
            ] );
            $fileIds[] = $fileId;
        }
        elseif( $fileId )
        {
            $elements[] = Elements::image( $fileId );
            $fileIds[] = $fileId;
        }
        elseif( !empty( $text ) )
        {
            $elements[] = Elements::create( 'html', ['text' => Utils::html( $text )] );
        }

        if( empty( $elements ) ) {
            return null;
        }

        return ['elements' => $elements, 'fileIds' => array_values( array_unique( $fileIds ) )];
    }
}

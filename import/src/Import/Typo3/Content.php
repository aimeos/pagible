<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Typo3;

use Aimeos\Cms\Import\Elements;
use Aimeos\Cms\Import\Embeds;
use Aimeos\Cms\Import\Files as BaseFiles;
use Aimeos\Cms\Import\Markdown;
use Aimeos\Cms\Utils;
use Illuminate\Support\Collection;


/**
 * Converts TYPO3 tt_content records into Pagible content elements.
 *
 * Supports the core content types and the accordion, carousel, tab, card group, timeline and
 * icon group elements of the Bootstrap Package.
 */
class Content
{
    /** @var array<string, array{table: string, columns: array<int, string>, fields: array<int, string>}> Item tables, their extra columns and file fields of the Bootstrap Package elements by CType */
    protected const ITEMS = [
        'tab' => ['table' => 'tx_bootstrappackage_tab_item', 'columns' => [], 'fields' => ['media']],
        'card_group' => ['table' => 'tx_bootstrappackage_card_group_item', 'columns' => ['subheader', 'link'], 'fields' => ['image']],
        'timeline' => ['table' => 'tx_bootstrappackage_timeline_item', 'columns' => ['date'], 'fields' => ['image']],
        'icon_group' => ['table' => 'tx_bootstrappackage_icon_group_item', 'columns' => ['subheader', 'link', 'icon'], 'fields' => ['icon_file']],
    ];

    /** @var array<int, string> Content types of plugins and generated content which can't be imported */
    protected const UNSUPPORTED = ['list', 'form_formframework', 'felogin_login', 'mailform', 'login', 'search', 'menu', 'script'];

    /** @var array<int, string> Content types of the Bootstrap Package carousels whose items are slides */
    protected const CAROUSELS = ['carousel', 'carousel_small', 'carousel_fullscreen'];

    /** @var array<int, string> Content types whose header and text are imported without warning */
    protected const KNOWN = ['header', 'text', 'textpic', 'textmedia', 'image', 'bullets', 'table', 'uploads', 'html', 'div', 'accordion',
        'gallery', 'media', 'external_media', 'listgroup', 'quote', 'csv', 'texticon', 'textcolumns', 'panel'];

    /** @var array<string, string> Columns of the sys_file records used to sort the files of uploads elements */
    protected const SORTING = ['name' => 'name', 'extension' => 'extension', 'creation_date' => 'creation_date',
        'modification_date' => 'modification_date', 'size' => 'size'];

    /**
     * Initializes the converter.
     *
     * @param \Closure(object): ?string $file Imports the file of the sys_file_reference record and returns the file ID
     * @param Html $rewriter Rewrites the files and links in the HTML of the content elements
     * @param \Closure(array<int, int>): Collection<int|string, mixed> $fileRefs Fetches the sys_file_reference records of the content elements grouped by content UID
     * @param \Closure(array<int, int>): Collection<int|string, mixed> $accordions Fetches the accordion items of the content elements grouped by content UID
     * @param \Closure(array<int, int>): Collection<int|string, mixed> $carousels Fetches the carousel items of the content elements grouped by content UID
     * @param \Closure(array<int, int>): Collection<int|string, mixed> $carouselRefs Fetches the sys_file_reference records of the carousel items grouped by item UID
     * @param (\Closure(array<int, int>): Collection<int|string, mixed>)|null $accordionRefs Fetches the sys_file_reference records of the accordion items grouped by item UID
     * @param (\Closure(object): ?string)|null $online Returns the URL of the YouTube or Vimeo video of the sys_file_reference record
     * @param (\Closure(object): ?string)|null $path Returns the URL or path of the file of the sys_file_reference record
     * @param (\Closure(string, array<int, int>, array<int, string>): Collection<int|string, mixed>)|null $items Fetches the items of the table with the extra columns grouped by content UID
     * @param (\Closure(string, array<int, int>, array<int, string>): Collection<int|string, mixed>)|null $itemRefs Fetches the sys_file_reference records of the fields of the items of the table grouped by item UID
     * @param (\Closure(array<int, int>): array{refs: Collection<int, mixed>, skipped: array<int, string>})|null $collections Returns the files of the file collections as file references and the types of the skipped collections
     * @param (\Closure(string): void)|null $warn Reports content which can't be imported
     * @param (\Closure(string): ?string)|null $contents Returns the content of the imported file by its ID or NULL if it can't be read
     */
    public function __construct(
        protected \Closure $file,
        protected Html $rewriter,
        protected \Closure $fileRefs,
        protected \Closure $accordions,
        protected \Closure $carousels,
        protected \Closure $carouselRefs,
        protected ?\Closure $accordionRefs = null,
        protected ?\Closure $online = null,
        protected ?\Closure $path = null,
        protected ?\Closure $items = null,
        protected ?\Closure $itemRefs = null,
        protected ?\Closure $collections = null,
        protected ?\Closure $warn = null,
        protected ?\Closure $contents = null
    ) {
        $this->refs = $this->accordionItems = $this->carouselItems = $this->carouselFileRefs = $this->accordionFileRefs = Collection::make();
        $this->groupItems = $this->groupRefs = array_fill_keys( array_keys( self::ITEMS ), Collection::make() );
    }


    /** @var Collection<int|string, mixed> sys_file_reference records of the prefetched content elements grouped by content UID */
    protected Collection $refs;

    /** @var Collection<int|string, mixed> Accordion items of the prefetched content elements grouped by content UID */
    protected Collection $accordionItems;

    /** @var Collection<int|string, mixed> Carousel items of the prefetched content elements grouped by content UID */
    protected Collection $carouselItems;

    /** @var Collection<int|string, mixed> sys_file_reference records of the prefetched carousel items grouped by item UID */
    protected Collection $carouselFileRefs;

    /** @var Collection<int|string, mixed> sys_file_reference records of the prefetched accordion items grouped by item UID */
    protected Collection $accordionFileRefs;

    /** @var array<string, Collection<int|string, mixed>> Items of the other Bootstrap Package elements grouped by CType and content UID */
    protected array $groupItems;

    /** @var array<string, Collection<int|string, mixed>> sys_file_reference records of the items of the other Bootstrap Package elements grouped by CType and item UID */
    protected array $groupRefs;

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

            foreach( $this->carouselItems->get( (int) ( $record->uid ?? 0 ), [] ) as $item ) {
                $texts[] = (string) ( $item->bodytext ?? '' );
            }

            foreach( $this->itemsOf( $record ) as $item ) {
                $texts[] = (string) ( $item->bodytext ?? '' );
            }
        }

        return $texts;
    }


    /**
     * Returns a copy of the converter which uses the given rewriter for the HTML of the content elements.
     */
    public function withRewriter( Html $rewriter ): static
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

        $carouselUids = array_merge( ...array_values( array_intersect_key( $types, array_flip( self::CAROUSELS ) ) ) );
        $carousels = $carouselUids ? ( $this->carousels )( $carouselUids ) : Collection::make();
        $accordions = isset( $types['accordion'] ) ? ( $this->accordions )( $types['accordion'] ) : Collection::make();

        $this->refs = $this->refs->union( ( $this->fileRefs )( $missing ) );
        $this->accordionItems = $this->accordionItems->union( $accordions );
        $this->accordionFileRefs = $this->accordionFileRefs->union( $this->accordionRefs && ( $new = $this->itemUids( $accordions ) ) ? ( $this->accordionRefs )( $new ) : [] );
        $this->carouselItems = $this->carouselItems->union( $carousels );
        $this->carouselFileRefs = $this->carouselFileRefs->union( ( $new = $this->itemUids( $carousels ) ) ? ( $this->carouselRefs )( $new ) : [] );

        foreach( self::ITEMS as $type => $config )
        {
            if( !isset( $types[$type] ) || !$this->items ) {
                continue;
            }

            $items = ( $this->items )( $config['table'], $types[$type], $config['columns'] );
            $refs = $this->itemRefs && ( $new = $this->itemUids( $items ) ) ? ( $this->itemRefs )( $config['table'], $new, $config['fields'] ) : [];

            $this->groupItems[$type] = $this->groupItems[$type]->union( $items );
            $this->groupRefs[$type] = $this->groupRefs[$type]->union( $refs );
        }

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
        $this->accordionFileRefs = $this->accordionFileRefs->only( $this->itemUids( $this->accordionItems ) );

        foreach( self::ITEMS as $type => $config )
        {
            $this->groupItems[$type] = $this->groupItems[$type]->only( $keep );
            $this->groupRefs[$type] = $this->groupRefs[$type]->only( $this->itemUids( $this->groupItems[$type] ) );
        }

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

        $refs = $this->refs->only( $keys )->flatten( 1 )
            ->concat( $this->carouselFileRefs->only( $this->itemUids( $this->carouselItems->only( $keys ) ) )->flatten( 1 ) )
            ->concat( $this->accordionFileRefs->only( $this->itemUids( $this->accordionItems->only( $keys ) ) )->flatten( 1 ) );

        foreach( self::ITEMS as $type => $config ) {
            $refs = $refs->concat( $this->groupRefs[$type]->only( $this->itemUids( $this->groupItems[$type]->only( $keys ) ) )->flatten( 1 ) );
        }

        return $refs;
    }


    /**
     * Returns the UIDs of the Bootstrap Package items.
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
            'text' => $this->text( $record ),
            'textpic', 'textmedia' => $this->textpic( $record ),
            'image', 'gallery' => $this->image( $record ),
            'media' => $this->textpic( $record ),
            'external_media' => $this->externalMedia( $record ),
            'listgroup' => $this->listgroup( $record ),
            'quote' => $this->quote( $record ),
            'csv' => $this->csv( $record ),
            'bullets' => $this->bullets( $record ),
            'table' => $this->table( $record ),
            'uploads' => $this->uploads( $record ),
            'html' => $this->html( $record ),
            'accordion' => $this->accordion( $record ),
            'carousel', 'carousel_small', 'carousel_fullscreen' => $this->carousel( $record ),
            'tab' => $this->tab( $record ),
            'card_group', 'icon_group' => $this->cards( $record ),
            'timeline' => $this->timeline( $record ),
            // dividers are only visual separators
            'div' => null,
            default => $this->default( $record ),
        };
    }


    /**
     * Converts a Bootstrap Package accordion into a questions element.
     *
     * The media of the items are added as images in front of their texts.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function accordion( object $record ): ?array
    {
        $items = [];
        $fileIds = [];

        foreach( $this->accordionItems->get( (int) ( $record->uid ?? 0 ), Collection::make() ) as $child )
        {
            $media = $this->mediaHtml( $this->accordionFileRefs->get( (int) ( $child->uid ?? 0 ), Collection::make() ) );

            if( empty( $child->header ) || ( empty( $child->bodytext ) && $media === '' ) ) {
                continue;
            }

            $result = $this->rte( $media . (string) ( $child->bodytext ?? '' ) );
            $items[] = ['title' => $child->header, 'text' => Markdown::fromHtml( $result['html'] )];
            $fileIds = array_merge( $fileIds, $result['fileIds'] );
        }

        // accordions without items show their header and text
        if( empty( $items ) ) {
            return $this->default( $record );
        }

        [$title, $elements] = $this->titled( $record );
        $elements[] = Elements::create( 'questions', ['title' => $title, 'items' => $items] );

        return ['elements' => $elements, 'fileIds' => array_values( array_unique( $fileIds ) )];
    }


    /**
     * Converts a bullet list content element into a text element or an html element for definition lists.
     *
     * The lines of the bodytext are the list items, the terms and definitions of the definition lists are separated by "|".
     * Like in TYPO3, the lines are plain text and HTML in them is shown as text.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function bullets( object $record ): ?array
    {
        $lines = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) ( $record->bodytext ?? '' ) ) ?: [] ), fn( $value ) => $value !== '' ) );
        $elements = $this->heading( $record );
        $fileIds = [];

        if( $lines !== [] )
        {
            $type = (int) ( $record->bullets_type ?? 0 );

            if( $type === 2 )
            {
                $html = '<dl>' . implode( '', array_map( function( $line ) {
                    [$term, $definition] = array_pad( explode( '|', $line, 2 ), 2, '' );
                    return '<dt>' . e( trim( $term ) ) . '</dt>' . ( trim( $definition ) !== '' ? '<dd>' . e( trim( $definition ) ) . '</dd>' : '' );
                }, $lines ) ) . '</dl>';

                $result = $this->rewriter->rewrite( $html );
                $elements[] = Elements::create( 'html', ['text' => Utils::html( $result['html'] )] );
            }
            else
            {
                $tag = $type === 1 ? 'ol' : 'ul';
                $result = $this->rewriter->rewrite( "<{$tag}><li>" . implode( '</li><li>', array_map( fn( $line ) => e( $line ), $lines ) ) . "</li></{$tag}>" );
                $elements[] = Elements::create( 'text', ['text' => Markdown::fromHtml( $result['html'] )] );
            }

            $fileIds = $result['fileIds'];
        }

        return $elements ? ['elements' => $elements, 'fileIds' => array_values( array_unique( $fileIds ) )] : null;
    }


    /**
     * Converts a Bootstrap Package card group or icon group into a cards element.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function cards( object $record ): ?array
    {
        $cards = $fileIds = [];
        $type = (string) ( $record->CType ?? '' );

        foreach( $this->itemsOf( $record ) as $child )
        {
            $refs = $this->groupRefs[$type]->get( (int) ( $child->uid ?? 0 ), Collection::make() );
            $this->iconSet( $child, $refs, (int) ( $record->uid ?? 0 ) );
            $card = $this->card( $child, $refs, $fileIds );

            if( $card !== null ) {
                $cards[] = $card;
            }
        }

        if( $cards === [] ) {
            return $this->default( $record );
        }

        [$title, $elements] = $this->titled( $record );
        $elements[] = Elements::create( 'cards', ['title' => $title, 'cards' => $cards] );

        return ['elements' => $elements, 'fileIds' => array_values( array_unique( $fileIds ) )];
    }


    /**
     * Returns the card of a Bootstrap Package item or NULL if it has no content.
     *
     * The subheader of the item is shown in front of its text and the button of carousel items after it.
     * Hidden headers of carousel items aren't used as title.
     *
     * @param Collection<int, mixed> $refs sys_file_reference records of the item, the first image is used
     * @param array<int, string> $fileIds File IDs used by the cards, the ID of the image is added
     * @return array<string, mixed>|null
     */
    protected function card( object $item, Collection $refs, array &$fileIds ): ?array
    {
        $url = $this->link( (string) ( $item->link ?? '' ) );
        $button = trim( (string) ( $item->button_text ?? '' ) );
        $html = ( ( $subheader = trim( (string) ( $item->subheader ?? '' ) ) ) !== '' ? '<p><strong>' . e( $subheader ) . '</strong></p>' : '' )
            . (string) ( $item->bodytext ?? '' )
            . ( $button !== '' && $url !== '' ? '<p>' . $this->anchor( $url, e( $button ) ) . '</p>' : '' );
        $result = trim( $html ) !== '' ? $this->rte( $html ) : ['html' => '', 'fileIds' => []];
        $title = (string) ( $item->header_layout ?? '' ) !== '100' ? trim( (string) ( $item->header ?? '' ) ) : '';
        $text = Markdown::fromHtml( $result['html'] );
        $card = [];

        foreach( $refs as $ref )
        {
            if( $this->kind( $ref ) === 'image' && ( $id = ( $this->file )( $ref ) ) )
            {
                $card['file'] = Elements::file( $id );
                $fileIds[] = $id;
                break;
            }
        }

        if( $title === '' && $text === '' && $card === [] ) {
            return null;
        }

        array_push( $fileIds, ...$result['fileIds'] );

        return ['title' => $title] + array_filter( ['text' => $text, 'url' => $url] ) + $card;
    }


    /**
     * Converts a Bootstrap Package carousel into a slideshow or an image element if it has only one image.
     *
     * Carousels whose items have texts become cards elements instead because slideshows only show images.
     * The images of the carousel items are used before their background images.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function carousel( object $record ): ?array
    {
        $items = self::listOf( $this->carouselItems->get( (int) ( $record->uid ?? 0 ) ) );
        $refsOf = fn( $child ) => $this->carouselFileRefs->get( (int) ( $child->uid ?? 0 ), Collection::make() )
            ->sortBy( fn( $ref ) => ( $ref->fieldname ?? 'background_image' ) === 'image' ? 0 : 1 )->values();

        if( $items->contains( fn( $child ) => trim( strip_tags( (string) ( $child->bodytext ?? '' ) ) ) !== '' ) )
        {
            $cards = $fileIds = [];

            foreach( $items as $child )
            {
                if( ( $card = $this->card( $child, $refsOf( $child ), $fileIds ) ) !== null ) {
                    $cards[] = $card;
                }
            }

            [$title, $elements] = $this->titled( $record );
            $elements[] = Elements::create( 'cards', ['title' => $title, 'cards' => $cards] );

            return ['elements' => $elements, 'fileIds' => array_values( array_unique( $fileIds ) )];
        }

        $files = [];
        $fileIds = [];

        foreach( $items as $child )
        {
            // each item is one slide, the background image is only used if the item has no image
            foreach( $refsOf( $child ) as $ref )
            {
                if( $id = ( $this->file )( $ref ) )
                {
                    $files[] = Elements::file( $id );
                    $fileIds[] = $id;
                    break;
                }
            }
        }

        if( $files === [] ) {
            return $this->default( $record );
        }

        // single images have no title, so the header is shown in front of them
        [$title, $elements] = count( $files ) > 1 ? $this->titled( $record ) : ['', $this->heading( $record )];
        $elements[] = count( $files ) > 1
            ? Elements::create( 'slideshow', ['title' => $title, 'files' => $files] )
            : Elements::create( 'image', ['file' => $files[0]] );

        return ['elements' => $elements, 'fileIds' => array_values( array_unique( $fileIds ) )];
    }


    /**
     * Converts a Bootstrap Package CSV element into a table element or file elements if the CSV data is in files.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function csv( object $record ): ?array
    {
        if( trim( (string) ( $record->bodytext ?? '' ) ) !== '' ) {
            return $this->table( $record );
        }

        // the Bootstrap Package renders the CSV file as table
        foreach( ( $contents = $this->contents ) ? $this->refsOf( $record ) : [] as $ref )
        {
            // the CSV file stays attached to the page like in TYPO3
            if( $this->kind( $ref ) === 'file' && ( $id = ( $this->file )( $ref ) ) && $contents && trim( $data = (string) $contents( $id ) ) !== ''
                && ( $table = $this->table( (object) ( ['bodytext' => $data] + get_object_vars( $record ) ) ) )
            ) {
                return ['elements' => $table['elements'], 'fileIds' => array_values( array_unique( [...$table['fileIds'], $id] ) )];
            }
        }

        $media = $this->media( $this->refsOf( $record ) );
        $elements = [...$this->heading( $record ), ...$media['elements']];

        return $elements ? ['elements' => $elements, 'fileIds' => $media['fileIds']] : null;
    }


    /**
     * Converts a Bootstrap Package external media element into an embedded video.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function externalMedia( object $record ): ?array
    {
        $elements = $this->heading( $record );
        $url = trim( (string) ( $record->external_media_source ?? '' ) );

        if( $url !== '' && ( $content = Embeds::iframeContent( $url, (string) ( $record->header ?? '' ) ) ) ) {
            array_push( $elements, ...$content['elements'] );
        } elseif( $url !== '' ) {
            $this->warn( sprintf( 'External media "%s" of content element [%d] can\'t be embedded', $url, (int) ( $record->uid ?? 0 ) ) );
        }

        if( ( $text = trim( (string) ( $record->bodytext ?? '' ) ) ) !== '' )
        {
            $result = $this->rte( $text );
            $elements[] = Elements::create( 'html', ['text' => Utils::html( $result['html'] )] );
        }

        return $elements ? ['elements' => $elements, 'fileIds' => $result['fileIds'] ?? []] : null;
    }


    /**
     * Converts a Bootstrap Package list group into a list, each line of the bodytext is one item.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function listgroup( object $record ): ?array
    {
        $lines = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) ( $record->bodytext ?? '' ) ) ?: [] ), fn( $value ) => $value !== '' ) );
        $elements = $this->heading( $record );
        $fileIds = [];

        if( $lines !== [] )
        {
            $result = $this->rewriter->rewrite( '<ul><li>' . implode( '</li><li>', array_map( fn( $line ) => e( $line ), $lines ) ) . '</li></ul>' );
            $elements[] = Elements::create( 'text', ['text' => Markdown::fromHtml( $result['html'] )] );
            $fileIds = $result['fileIds'];
        }

        return $elements ? ['elements' => $elements, 'fileIds' => $fileIds] : null;
    }


    /**
     * Converts a Bootstrap Package quote into a blockquote with its source.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function quote( object $record ): ?array
    {
        $elements = $this->heading( $record );
        $text = trim( (string) ( $record->bodytext ?? '' ) );
        $source = trim( (string) ( $record->quote_source ?? '' ) );

        if( $text === '' && $source === '' ) {
            return $elements ? ['elements' => $elements, 'fileIds' => []] : null;
        }

        // plain text quotes keep their line breaks, the lines of rich text quotes become paragraphs like in TYPO3
        $result = match( true ) {
            $text === '' => ['html' => '', 'fileIds' => []],
            $text === strip_tags( $text ) => ['html' => '<p>' . nl2br( e( $text ) ) . '</p>', 'fileIds' => []],
            default => $this->rte( $text ),
        };

        if( $source !== '' )
        {
            // the link is already rewritten by link()
            $link = $this->link( (string) ( $record->quote_link ?? '' ) );
            $result['html'] .= '<p>— ' . ( $link !== '' ? $this->anchor( $link, e( $source ) ) : e( $source ) ) . '</p>';
        }

        $elements[] = Elements::create( 'text', ['text' => Markdown::fromHtml( '<blockquote>' . $result['html'] . '</blockquote>' )] );

        return ['elements' => $elements, 'fileIds' => $result['fileIds']];
    }


    /**
     * Converts a content element of another type with bodytext into an html element.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function default( object $record ): ?array
    {
        $type = (string) ( $record->CType ?? '' );
        $elements = $this->heading( $record );
        $fileIds = [];

        // plugins, forms and menus are generated by TYPO3 and only their header can be imported
        if( in_array( $type, self::UNSUPPORTED, true ) || str_starts_with( $type, 'menu_' ) )
        {
            $this->warn( sprintf( 'Content element [%d] of type "%s" can\'t be imported%s', (int) ( $record->uid ?? 0 ), $type, $elements ? ', only its header is used' : '' ) );
            return $elements ? ['elements' => $elements, 'fileIds' => []] : null;
        }

        if( !empty( $text = trim( (string) ( $record->bodytext ?? '' ) ) ) )
        {
            $result = $this->rte( $text );
            $elements[] = Elements::create( 'html', ['text' => Utils::html( $result['html'] )] );
            $fileIds = $result['fileIds'];
        }

        // files of other content types, e.g. the icons of Bootstrap Package text and icon elements
        $refs = $this->refsOf( $record );
        $media = $this->media( $refs );
        array_push( $elements, ...$media['elements'] );

        if( $type === 'texticon' ) {
            $this->iconSet( $record, $refs, (int) ( $record->uid ?? 0 ) );
        }
        array_push( $fileIds, ...$media['fileIds'] );

        // plugins registered as own content types, e.g. "news_pi1" in TYPO3 12+, are generated by TYPO3 too
        if( !in_array( $type, [...self::KNOWN, ...self::CAROUSELS, ...array_keys( self::ITEMS )], true ) && empty( $record->_pagible_container ) )
        {
            $this->warn( sprintf( 'Content element [%d] of unknown type "%s" %s', (int) ( $record->uid ?? 0 ), $type,
                $elements ? 'may not be imported completely, only its header, text and files are used' : 'has no content to import' ) );
        }

        return $elements ? ['elements' => $elements, 'fileIds' => array_values( array_unique( $fileIds ) )] : null;
    }


    /**
     * Reports icons of the Bootstrap Package icon sets which can't be imported because they aren't files.
     *
     * @param object $record Text and icon element or icon group item
     * @param Collection<int, mixed> $refs sys_file_reference records of the element or item
     * @param int $uid UID of the content element
     */
    protected function iconSet( object $record, Collection $refs, int $uid ): void
    {
        if( ( $icon = trim( (string) ( $record->icon ?? '' ) ) ) !== ''
            && !$refs->contains( fn( $ref ) => in_array( (string) ( $ref->fieldname ?? '' ), ['', 'icon_file'], true ) )
        ) {
            $this->warn( sprintf( 'Icon "%s" of content element [%d] is from an icon set and can\'t be imported', $icon, $uid ) );
        }
    }


    /**
     * Returns the items of the Bootstrap Package element in their order.
     *
     * @return Collection<int, mixed> Items
     */
    protected function itemsOf( object $record ): Collection
    {
        $items = ( $this->groupItems[(string) ( $record->CType ?? '' )] ?? Collection::make() )->get( (int) ( $record->uid ?? 0 ) );

        return self::listOf( $items );
    }


    /**
     * Returns the grouped records as list.
     *
     * @param mixed $records Collection or array of records or NULL if there are none
     * @return Collection<int, mixed> Records
     */
    protected static function listOf( mixed $records ): Collection
    {
        return $records instanceof Collection ? $records->values() : Collection::make( is_array( $records ) ? array_values( $records ) : [] );
    }


    /**
     * Returns the HTML link with the already escaped content.
     *
     * @param string $url Rewritten URL of the link
     * @param string $html Escaped HTML content of the link
     * @param string $target Target of the link, only "_blank" is kept by the HTML sanitizer
     */
    protected function anchor( string $url, string $html, string $target = '' ): string
    {
        return '<a href="' . e( $url ) . '"' . ( $target !== '' ? ' target="' . e( $target ) . '"' : '' ) . '>' . $html . '</a>';
    }


    /**
     * Returns "_blank" if the TYPO3 link like "t3://page?uid=1 _blank - "Title"" opens in a new window or an empty string.
     *
     * Popup windows like "800x600" open in a new window too.
     */
    protected function target( string $link ): string
    {
        $target = preg_split( '/\s+/', trim( $link ) )[1] ?? '';
        return $target === '_blank' || preg_match( '/^\d+x\d+(?::|$)/', $target ) ? '_blank' : '';
    }


    /**
     * Returns the URL of the TYPO3 link like "t3://page?uid=1 _blank" or an empty string if it can't be resolved.
     */
    protected function link( string $link ): string
    {
        return $this->rewriter->href( $link );
    }


    /**
     * Rewrites the files and links of the RTE text and wraps its lines outside of block elements into paragraphs like TYPO3.
     *
     * @return array{html: string, fileIds: array<int, string>}
     */
    protected function rte( string $html ): array
    {
        $result = $this->rewriter->rewrite( $html );
        return ['html' => Html::paragraphs( $result['html'] )] + $result;
    }


    /**
     * Reports content which can't be imported.
     */
    protected function warn( string $message ): void
    {
        if( $this->warn ) {
            ( $this->warn )( $message );
        }
    }


    /**
     * Returns the sys_file_reference records of the field of the tt_content record shown by TYPO3 for its content type.
     *
     * Files of other fields, e.g. the "image" field of textmedia elements whose type was changed
     * from textpic, aren't shown by TYPO3 and therefore skipped.
     *
     * @return Collection<int, mixed> File references in their order
     */
    protected function refsOf( object $record ): Collection
    {
        $uid = (int) ( $record->uid ?? 0 );
        $refs = isset( $this->prefetched[$uid] ) ? $this->refs->get( $uid ) : ( $this->fileRefs )( [$uid] )->get( $uid );
        $refs = self::listOf( $refs );

        $field = match( (string) ( $record->CType ?? '' ) ) {
            'textpic', 'image', 'gallery' => 'image',
            'textmedia', 'media' => 'assets',
            'uploads' => 'media',
            default => null,
        };

        return $field !== null
            ? $refs->filter( fn( $ref ) => !isset( $ref->fieldname ) || (string) $ref->fieldname === '' || $ref->fieldname === $field )->values()
            : $refs;
    }


    /**
     * Returns the kind of the referenced file.
     *
     * @return string "online" for YouTube and Vimeo videos, "image", "video", "audio" or "file"
     */
    protected function kind( object $ref ): string
    {
        // references without file record can't be imported as other media type
        if( !( ( $sysFile = get_object_vars( $ref )['sys_file'] ?? null ) instanceof \stdClass ) ) {
            return 'image';
        }

        $extension = strtolower( (string) ( $sysFile->extension ?? '' ) ?: pathinfo( (string) ( $sysFile->identifier ?? '' ), PATHINFO_EXTENSION ) );
        $mime = strtolower( (string) ( $sysFile->mime_type ?? '' ) );

        if( in_array( $extension, ['youtube', 'vimeo'], true ) || in_array( $mime, ['video/youtube', 'video/vimeo'], true ) ) {
            return 'online';
        }

        foreach( ['image', 'video', 'audio'] as $kind )
        {
            if( str_starts_with( $mime, $kind . '/' ) || str_starts_with( BaseFiles::mime( 'file.' . $extension ), $kind . '/' ) ) {
                return $kind;
            }
        }

        return 'file';
    }


    /**
     * Converts the file references into media elements.
     *
     * Consecutive images become an image element or a slideshow if there are several ones,
     * videos and audio files become video and audio elements, YouTube and Vimeo videos are
     * embedded and other files become file elements.
     *
     * @param Collection<int, mixed> $refs sys_file_reference records
     * @param string $title Title of the slideshow
     * @param array<int, mixed> $skip File references which are already used
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}
     */
    protected function media( Collection $refs, string $title = '', array $skip = [] ): array
    {
        $elements = $fileIds = $images = [];

        /** @param array<int, string> $images */
        $flush = function( array &$images ) use ( &$elements, $title ) {
            if( count( $images ) > 1 ) {
                $elements[] = Elements::create( 'slideshow', ['title' => $title, 'files' => array_map( fn( $id ) => Elements::file( $id ), $images )] );
            } elseif( $images ) {
                $elements[] = Elements::image( $images[0] );
            }

            $images = [];
        };

        foreach( $refs as $ref )
        {
            if( in_array( $ref, $skip, true ) ) {
                continue;
            }

            $kind = $this->kind( $ref );

            if( $kind === 'online' )
            {
                $flush( $images );
                $url = $this->online ? ( $this->online )( $ref ) : null;

                if( $url && ( $content = Embeds::iframeContent( $url, (string) ( $ref->title ?? '' ) ) ) ) {
                    array_push( $elements, ...$content['elements'] );
                }

                continue;
            }

            // linked images become html elements because image elements can't contain links
            if( $kind === 'image' && ( $linked = $this->linkedImage( $ref ) ) )
            {
                $flush( $images );
                $elements[] = Elements::create( 'html', ['text' => Utils::html( $linked['html'] )] );
                array_push( $fileIds, ...$linked['fileIds'] );
                continue;
            }

            if( !( $id = ( $this->file )( $ref ) ) ) {
                continue;
            }

            $fileIds[] = $id;

            if( $kind === 'image' ) {
                $images[] = $id;
            } else {
                $flush( $images );
                $elements[] = Elements::create( $kind, ['file' => Elements::file( $id )] );
            }
        }

        $flush( $images );

        return ['elements' => $elements, 'fileIds' => array_values( array_unique( $fileIds ) )];
    }


    /**
     * Returns the HTML of a linked image whose URLs are rewritten by Html or NULL if the image has no valid link.
     *
     * @return array{html: string, fileIds: array<int, string>}|null
     */
    protected function linkedImage( object $ref ): ?array
    {
        if( !$this->path || ( $url = $this->link( (string) ( $ref->link ?? '' ) ) ) === '' || !( $path = ( $this->path )( $ref ) ) ) {
            return null;
        }

        $result = $this->rewriter->rewrite( '<img src="' . e( $path ) . '" alt="' . e( (string) ( $ref->alternative ?? '' ) ) . '">' );

        return $result['fileIds'] ? ['html' => '<p>' . $this->anchor( $url, $result['html'], $this->target( (string) ( $ref->link ?? '' ) ) ) . '</p>', 'fileIds' => $result['fileIds']] : null;
    }


    /**
     * Returns the HTML image tags of the referenced images whose URLs are rewritten by Html.
     *
     * @param iterable<object> $refs sys_file_reference records
     */
    protected function mediaHtml( iterable $refs ): string
    {
        $html = '';

        foreach( $refs as $ref )
        {
            if( $this->path && $this->kind( $ref ) === 'image' && ( $path = ( $this->path )( $ref ) ) ) {
                $html .= '<p><img src="' . e( $path ) . '" alt="' . e( (string) ( $ref->alternative ?? '' ) ) . '"></p>';
            }
        }

        return $html;
    }


    /**
     * Converts a header content element into a heading element.
     *
     * @return array{elements: array<int, array<string, mixed>>}|null
     */
    protected function header( object $record ): ?array
    {
        return ( $elements = $this->heading( $record ) ) ? ['elements' => $elements] : null;
    }


    /**
     * Maps the TYPO3 header_layout to the heading level.
     */
    protected function headerLevel( mixed $layout ): int
    {
        return is_scalar( $layout ) && in_array( (string) $layout, ['1', '2', '3', '4', '5', '6'], true ) ? (int) $layout : 2;
    }


    /**
     * Returns the heading and subheader elements of a content record unless its header is hidden.
     *
     * Headers with links become html elements because headings can't contain links.
     *
     * @return list<array<string, mixed>>
     */
    protected function heading( object $record ): array
    {
        if( (string) ( $record->header_layout ?? '0' ) === '100' ) {
            return [];
        }

        $elements = [];

        // TYPO3 shows the subheader and the date even if there's no header
        if( trim( $header = (string) ( $record->header ?? '' ) ) !== '' )
        {
            $level = $this->headerLevel( $record->header_layout ?? null );
            $link = (string) ( $record->header_link ?? '' );

            if( ( $url = $this->link( $link ) ) !== '' )
            {
                $html = $this->anchor( $url, e( $header ), $this->target( $link ) );
                $elements[] = Elements::create( 'html', ['text' => Utils::html( "<h{$level}>{$html}</h{$level}>" )] );
            }
            else
            {
                $elements[] = Elements::create( 'heading', ['level' => $level, 'title' => $header] );
            }
        }

        // like in fluid_styled_content, the subheader is a heading one level below the header
        if( ( $subheader = trim( (string) ( $record->subheader ?? '' ) ) ) !== '' ) {
            $elements[] = Elements::create( 'heading', ['level' => min( $this->headerLevel( $record->header_layout ?? null ) + 1, 6 ), 'title' => $subheader] );
        }

        if( ( $date = (int) ( $record->date ?? 0 ) ) > 0 ) {
            $elements[] = Elements::create( 'text', ['text' => date( 'Y-m-d', $date )] );
        }

        return $elements;
    }


    /**
     * Returns the title of an element with items and the header elements shown in front of it.
     *
     * Hidden headers aren't used as title. Headers with link, subheader, date or another level
     * than the default one are shown as header elements in front because titles can only be plain text.
     *
     * @return array{0: string, 1: list<array<string, mixed>>} Title and header elements
     */
    protected function titled( object $record ): array
    {
        if( (string) ( $record->header_layout ?? '0' ) === '100' ) {
            return ['', []];
        }

        $plain = trim( (string) ( $record->header_link ?? '' ) ) === '' && trim( (string) ( $record->subheader ?? '' ) ) === ''
            && (int) ( $record->date ?? 0 ) <= 0 && $this->headerLevel( $record->header_layout ?? null ) === 2;

        return $plain ? [trim( (string) ( $record->header ?? '' ) ), []] : ['', $this->heading( $record )];
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
     * Converts an image content element into an image element or a slideshow for several images.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function image( object $record ): ?array
    {
        // the header is shown as heading, so the slideshow has no title of its own
        $media = $this->media( $this->refsOf( $record ) );

        // the header is shown by TYPO3 even if the images are missing
        if( ( $elements = [...$this->heading( $record ), ...$media['elements']] ) === [] ) {
            return null;
        }

        return ['elements' => $elements, 'fileIds' => $media['fileIds']];
    }


    /**
     * Maps the TYPO3 imageorient of images next to the text to the Pagible position.
     *
     * Images in the text (17, 18) float, images beside the text (25, 26) are placed in a grid.
     *
     * @return string|null Position or NULL if the images are above or below the text
     */
    protected function imagePosition( int $orient ): ?string
    {
        return match( $orient ) {
            17 => 'end',
            18 => 'start',
            25 => 'grid-end',
            26 => 'grid-start',
            default => null,
        };
    }


    /**
     * Converts a table content element into a table element.
     *
     * The rows are the lines of the bodytext, the cells are separated by the configured delimiter
     * and enclosure and enclosed cells can span several lines. Links in the cells are rewritten and
     * kept as Markdown, other HTML is removed. Table footers aren't supported by table elements,
     * the last row is a normal row.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function table( object $record ): ?array
    {
        // chr() uses the lowest byte of the character codes too
        $delimiter = chr( ( (int) ( $record->table_delimiter ?? 124 ) ?: 124 ) & 255 );
        $enclosure = ( $code = (int) ( $record->table_enclosure ?? 0 ) ) ? chr( $code & 255 ) : "\0";
        $rows = $fileIds = [];

        if( ( $fh = fopen( 'php://temp', 'w+' ) ) === false ) {
            return null;
        }

        fwrite( $fh, trim( str_replace( ["\r\n", "\r"], "\n", (string) ( $record->bodytext ?? '' ) ) ) );
        rewind( $fh );

        // like in TYPO3, empty lines are empty rows
        while( ( $row = fgetcsv( $fh, null, $delimiter, $enclosure, '' ) ) !== false )
        {
            $rows[] = array_map( function( $cell ) use ( &$fileIds ) {
                return $this->cell( (string) $cell, $fileIds );
            }, $row === [null] ? [''] : $row );
        }

        fclose( $fh );

        if( implode( '', array_merge( ...$rows ?: [[]] ) ) === '' ) {
            return $this->heading( $record ) ? ['elements' => $this->heading( $record ), 'fileIds' => []] : null;
        }

        // all rows have the same number of cells, TYPO3 limits them to the configured number of columns
        $count = ( $cols = (int) ( $record->cols ?? 0 ) ) > 0 ? $cols : max( 1, ...array_map( 'count', $rows ) );
        $rows = array_map( fn( $row ) => array_slice( array_pad( $row, $count, '' ), 0, $count ), $rows );

        $element = Elements::create( 'table', [
            'title' => trim( (string) ( $record->table_caption ?? '' ) ),
            'header' => match( (int) ( $record->table_header_position ?? 0 ) ) { 1 => 'row', 2 => 'col', default => '' },
            'table' => $rows,
        ] );

        return ['elements' => [...$this->heading( $record ), $element], 'fileIds' => array_values( array_unique( $fileIds ) )];
    }


    /**
     * Returns the text of a table cell, links are kept as Markdown links because the cells are rendered as Markdown.
     *
     * @param array<int, string> $fileIds File IDs used by the table, the ones of the linked files are added
     */
    protected function cell( string $cell, array &$fileIds ): string
    {
        if( !str_contains( $cell, '<' ) ) {
            return trim( (string) preg_replace( '/\s+/', ' ', html_entity_decode( $cell, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
        }

        $result = $this->rewriter->rewrite( $cell );
        array_push( $fileIds, ...$result['fileIds'] );

        // other tags than links are removed, the cells are single lines
        $html = strip_tags( (string) preg_replace( '/<br\s*\/?>/i', ' ', $result['html'] ), '<a><strong><b><em><i>' );

        return trim( (string) preg_replace( '/\s+/', ' ', Markdown::fromHtml( $html ) ) );
    }


    /**
     * Converts a text content element into a text element.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function text( object $record ): ?array
    {
        $elements = $this->heading( $record );
        $fileIds = [];

        if( ( $text = trim( (string) ( $record->bodytext ?? '' ) ) ) !== '' )
        {
            $result = $this->rte( $text );
            $elements[] = Elements::create( 'html', ['text' => Utils::html( $result['html'] )] );
            $fileIds = $result['fileIds'];
        }

        return $elements ? ['elements' => $elements, 'fileIds' => array_values( array_unique( $fileIds ) )] : null;
    }


    /**
     * Converts a textpic/textmedia content element into an image-text element or media and text elements.
     *
     * One image next to the text becomes an image-text element, images above or below the text
     * and further media become separate elements in the same order like in TYPO3.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function textpic( object $record ): ?array
    {
        $elements = $this->heading( $record );
        $orient = (int) ( $record->imageorient ?? 0 );
        $position = $this->imagePosition( $orient );
        $text = trim( (string) ( $record->bodytext ?? '' ) );
        $refs = $this->refsOf( $record );
        $fileIds = [];
        $skip = [];

        if( $text !== '' )
        {
            $result = $this->rte( $text );
            $text = $result['html'];
            $fileIds = $result['fileIds'];
        }

        $textElement = null;

        if( $text !== '' && $position && ( $first = $refs->first( fn( $ref ) => $this->kind( $ref ) === 'image' ) )
            && ( $fileId = ( $this->file )( $first ) )
        ) {
            $textElement = Elements::create( 'image-text', [
                'text' => Markdown::fromHtml( $text ),
                'file' => Elements::file( $fileId ),
                'position' => $position,
            ] );
            $fileIds[] = $fileId;
            $skip[] = $first;
        }
        elseif( $text !== '' )
        {
            $textElement = Elements::create( 'html', ['text' => Utils::html( $text )] );
        }

        $media = $this->media( $refs, '', $skip );
        $fileIds = array_merge( $fileIds, $media['fileIds'] );

        // media below the text (8, 9, 10) are shown after the text, all others before
        $parts = in_array( $orient, [8, 9, 10], true ) || $textElement && $skip
            ? [$textElement, ...$media['elements']]
            : [...$media['elements'], $textElement];

        array_push( $elements, ...array_filter( $parts ) );

        if( empty( $elements ) ) {
            return null;
        }

        return ['elements' => $elements, 'fileIds' => array_values( array_unique( $fileIds ) )];
    }


    /**
     * Converts an uploads content element into file elements.
     *
     * The files of the referenced file collections follow the directly referenced files and
     * all files are sorted like in TYPO3 if a sorting is configured. Collections by category
     * aren't supported.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function uploads( object $record ): ?array
    {
        $elements = $this->heading( $record );
        $refs = $this->refsOf( $record );
        $fileIds = [];

        $uids = array_values( array_filter( array_map( 'intval', explode( ',', (string) ( $record->file_collections ?? '' ) ) ) ) );

        if( $uids && $this->collections )
        {
            $result = ( $this->collections )( $uids );
            $refs = $refs->concat( $result['refs'] )->values();

            foreach( $result['skipped'] as $uid => $type ) {
                $this->warn( sprintf( 'File collection [%d] of type "%s" used by content element [%d] can\'t be imported', $uid, $type, (int) ( $record->uid ?? 0 ) ) );
            }
        }

        if( $column = self::SORTING[(string) ( $record->filelink_sorting ?? '' )] ?? null )
        {
            // TYPO3 compares all properties in natural case-insensitive order and reverses the list for descending order
            $value = fn( $ref ) => ( $file = get_object_vars( $ref )['sys_file'] ?? null ) instanceof \stdClass ? (string) ( $file->{$column} ?? '' ) : '';
            $refs = $refs->sort( fn( $a, $b ) => strnatcasecmp( $value( $a ), $value( $b ) ) )->values();
            $refs = strtolower( (string) ( $record->filelink_sorting_direction ?? '' ) ) === 'desc' ? $refs->reverse()->values() : $refs;
        }

        foreach( $refs as $ref )
        {
            if( $id = ( $this->file )( $ref ) )
            {
                $elements[] = Elements::create( 'file', ['file' => Elements::file( $id )] );
                $fileIds[] = $id;
            }
        }

        return $elements ? ['elements' => $elements, 'fileIds' => array_values( array_unique( $fileIds ) )] : null;
    }


    /**
     * Converts a Bootstrap Package tab element into a questions element.
     *
     * The media of the tabs are added as images in front of their texts.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function tab( object $record ): ?array
    {
        $items = $fileIds = [];

        foreach( $this->itemsOf( $record ) as $child )
        {
            $media = $this->mediaHtml( $this->groupRefs['tab']->get( (int) ( $child->uid ?? 0 ), Collection::make() ) );

            if( trim( (string) ( $child->header ?? '' ) ) === '' || ( trim( (string) ( $child->bodytext ?? '' ) ) === '' && $media === '' ) ) {
                continue;
            }

            $result = $this->rte( $media . (string) $child->bodytext );
            $items[] = ['title' => trim( (string) $child->header ), 'text' => Markdown::fromHtml( $result['html'] )];
            array_push( $fileIds, ...$result['fileIds'] );
        }

        if( $items === [] ) {
            return $this->default( $record );
        }

        [$title, $elements] = $this->titled( $record );
        $elements[] = Elements::create( 'questions', ['title' => $title, 'items' => $items] );

        return ['elements' => $elements, 'fileIds' => array_values( array_unique( $fileIds ) )];
    }


    /**
     * Converts a Bootstrap Package timeline into a timeline element.
     *
     * The dates of the items are the labels, their images are added in front of their texts.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function timeline( object $record ): ?array
    {
        $items = $fileIds = [];

        foreach( $this->itemsOf( $record ) as $child )
        {
            if( ( $title = trim( (string) ( $child->header ?? '' ) ) ) === '' ) {
                continue;
            }

            $media = $this->mediaHtml( $this->groupRefs['timeline']->get( (int) ( $child->uid ?? 0 ), Collection::make() ) );
            $result = $this->rte( $media . (string) ( $child->bodytext ?? '' ) );
            $date = $child->date ?? '';
            $label = is_numeric( $date ) ? ( (int) $date > 0 ? date( 'Y-m-d', (int) $date ) : '' ) : trim( (string) $date );

            $items[] = ['label' => $label, 'title' => $title, 'text' => Markdown::fromHtml( $result['html'] )];
            array_push( $fileIds, ...$result['fileIds'] );
        }

        if( $items === [] ) {
            return $this->default( $record );
        }

        [$title, $elements] = $this->titled( $record );
        $elements[] = Elements::create( 'timeline', ['title' => $title, 'items' => $items] );

        return ['elements' => $elements, 'fileIds' => array_values( array_unique( $fileIds ) )];
    }
}

<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Illuminate\Support\Collection;


/**
 * Determines the page hierarchy of the Drupal nodes from the menu, the book outlines and the URL aliases.
 */
class DrupalTree
{
    /** @var array{roots: array<int, array{nid: int|null, uuid: string, title: string, uri: string}>, children: array<string, array<int, array{nid: int|null, uuid: string, title: string, uri: string}>>} Menu links by parent key */
    protected array $menu = ['roots' => [], 'children' => []];
    /** @var array<int, array<string, string>> Menu link titles by node ID and Drupal language code */
    protected array $titles = [];
    /** @var array<string, array<string, string>> Translated menu link titles by link UUID and Drupal language code */
    protected array $linkTitles = [];
    /** @var array<string, int> Node IDs by their default language alias */
    protected array $aliasNids = [];
    /** @var array<int, int|string> Default language aliases by node ID */
    protected array $nidAliases = [];
    /** @var array<int, int>|null Parent node IDs of the nodes in the book outlines by node ID */
    protected ?array $books = null;
    /** @var array<int, int> Weights of the nodes in the book outlines by node ID */
    protected array $bookWeights = [];


    /**
     * Initializes the object.
     *
     * @param DrupalReader $reader Reader for the Drupal database
     * @param DrupalData $data Configuration of the Drupal database
     * @param DrupalUrls $urls URL aliases of the imported nodes
     * @param Collection<int|string, mixed> $nodes Imported nodes by node ID
     * @param Collection<int, \stdClass> $links Menu links of all languages
     */
    public function __construct( protected DrupalReader $reader, protected DrupalData $data, protected DrupalUrls $urls, protected Collection $nodes, Collection $links )
    {
        $this->menu = $this->map( $links->filter( fn( $link ) => !empty( $link->default_langcode ) )->values() );
        $this->titles = $this->linkNames( $links );

        foreach( $links->filter( fn( $link ) => empty( $link->default_langcode ) && trim( (string) $link->title ) !== '' ) as $link ) {
            $this->linkTitles[(string) $link->uuid][(string) $link->langcode] = trim( (string) $link->title );
        }

        foreach( $nodes as $node )
        {
            if( ( $alias = $this->urls->alias( (int) $node->nid, $node->langcode ?? '', true ) ) !== null ) {
                $this->aliasNids[trim( $alias, '/' )] = (int) $node->nid;
            }
        }

        $this->nidAliases = array_flip( $this->aliasNids );
    }


    /**
     * Returns the menu links shown at the top level.
     *
     * @return array<int, array{nid: int|null, uuid: string, title: string, uri: string}>
     */
    public function roots(): array
    {
        return $this->menu['roots'];
    }


    /**
     * Returns the menu links below the parent menu link.
     *
     * @param string $key Key of the parent menu link, e.g. "node:42" or "link:<uuid>"
     * @return array<int, array{nid: int|null, uuid: string, title: string, uri: string}>
     */
    public function children( string $key ): array
    {
        return $this->menu['children'][$key] ?? [];
    }


    /**
     * Returns the menu link titles of the node.
     *
     * @return array<string, string> Titles by Drupal language code
     */
    public function names( int $nid ): array
    {
        return $this->titles[$nid] ?? [];
    }


    /**
     * Returns the translated title of the menu link.
     *
     * @param string $uuid UUID of the menu link
     * @param string $langcode Drupal language code
     */
    public function title( string $uuid, string $langcode ): ?string
    {
        return $this->linkTitles[$uuid][$langcode] ?? null;
    }


    /**
     * Returns the IDs of the nodes in the order of the menu and the book outlines.
     *
     * @return array<int, int> Node IDs, the ones of the menu depth-first first
     */
    public function order(): array
    {
        $nids = [];
        $links = array_reverse( $this->menu['roots'] );

        // depth-first like importLink(), the next link is at the end of the stack
        while( $link = array_pop( $links ) )
        {
            if( $link['nid'] ) {
                $nids[] = $link['nid'];
            }

            $key = $link['nid'] ? 'node:' . $link['nid'] : 'link:' . $link['uuid'];
            array_push( $links, ...array_reverse( $this->menu['children'][$key] ?? [] ) );
        }

        return [...$nids, ...$this->bookOrder()];
    }


    /**
     * Returns the IDs of the nodes in the order they are imported: the front page, the nodes in the menu and all others.
     *
     * @return array<int, int> Unique node IDs of the imported nodes
     */
    public function sequence(): array
    {
        $order = [];

        foreach( [...array_filter( [$this->urls->frontNode()] ), ...$this->order(), ...$this->nodes->keys()->all()] as $nid )
        {
            if( is_int( $nid ) && $this->nodes->has( $nid ) && !isset( $order[$nid] ) ) {
                $order[$nid] = count( $order );
            }
        }

        return array_keys( $order );
    }


    /**
     * Returns the ID of the parent node in the book outline or the one of the parent alias.
     *
     * @return int|null Node ID or NULL if the node has no parent
     */
    public function parent( int $nid ): ?int
    {
        return $this->bookParent( $nid ) ?? $this->aliasParent( $nid );
    }


    /**
     * Builds a menu map with the root links and the child links by parent key.
     *
     * Links without target like "<nolink>" and links to the front page aren't imported,
     * their child links are added to the next parent link instead.
     *
     * @param Collection<int, \stdClass> $menuTree Menu links in the default language
     * @return array{roots: array<int, array{nid: int|null, uuid: string, title: string, uri: string}>, children: array<string, array<int, array{nid: int|null, uuid: string, title: string, uri: string}>>}
     */
    protected function map( Collection $menuTree ): array
    {
        $map = ['roots' => [], 'children' => []];
        $byUuid = $menuTree->keyBy( fn( $link ) => 'menu_link_content:' . $link->uuid );
        $nids = $menuTree->mapWithKeys( fn( $link ) => [(string) $link->uuid => $this->urls->linkNid( $link )] );
        $skip = fn( \stdClass $link ) => in_array( $link->link__uri ?? '', ['route:<nolink>', 'route:<none>', 'route:<button>', 'route:<front>', 'internal:/<front>'], true );

        foreach( $menuTree as $link )
        {
            if( $skip( $link ) ) {
                continue;
            }

            $parent = !empty( $link->parent ) ? $byUuid->get( $link->parent ) : null;

            for( $seen = []; $parent && $skip( $parent ) && !isset( $seen[$parent->uuid] ); ) {
                $seen[$parent->uuid] = true;
                $parent = !empty( $parent->parent ) ? $byUuid->get( $parent->parent ) : null;
            }

            $item = [
                'nid' => $nids->get( (string) $link->uuid ),
                'uuid' => (string) $link->uuid,
                'title' => trim( (string) $link->title ),
                'uri' => (string) ( $link->link__uri ?? '' ),
            ];

            if( $parent && !$skip( $parent ) ) {
                $map['children'][( $nid = $nids->get( (string) $parent->uuid ) ) ? 'node:' . $nid : 'link:' . $parent->uuid][] = $item;
            } else {
                $map['roots'][] = $item;
            }
        }

        return $map;
    }


    /**
     * Returns the menu link titles of the nodes in all languages.
     *
     * The first link of a node in the menu order is used if several links point to the same node.
     *
     * @param Collection<int, \stdClass> $links Menu links of all languages
     * @return array<int, array<string, string>> Titles by node ID and Drupal language code
     */
    protected function linkNames( Collection $links ): array
    {
        $nids = [];
        $titles = [];

        // translations share the link target of the link in the default language
        foreach( $links->filter( fn( $link ) => !empty( $link->default_langcode ) ) as $link ) {
            $nids[$link->uuid] ??= $this->urls->linkNid( $link );
        }

        foreach( $links as $link )
        {
            if( ( $nid = $nids[$link->uuid] ?? null ) && trim( (string) $link->title ) !== '' ) {
                $titles[$nid][(string) $link->langcode] ??= trim( (string) $link->title );
            }
        }

        return $titles;
    }


    /**
     * Returns the node ID of the parent alias of the node, e.g. of "/about" for "/about/team".
     */
    protected function aliasParent( int $nid ): ?int
    {
        $path = $this->nidAliases[$nid] ?? null;
        $parts = $path !== null ? explode( '/', (string) $path ) : [];

        while( count( $parts ) > 1 )
        {
            array_pop( $parts );

            if( ( $parentNid = $this->aliasNids[implode( '/', $parts )] ?? null ) && $parentNid !== $nid ) {
                return $parentNid;
            }
        }

        return null;
    }


    /**
     * Returns the ID of the parent node in the book outline of the Book module.
     *
     * @return int|null Node ID or NULL if the node isn't part of a book, is the top of a book or its parent isn't imported
     */
    protected function bookParent( int $nid ): ?int
    {
        $pid = $this->books()[$nid] ?? 0;

        return $pid && $pid !== $nid && $this->nodes->has( $pid ) ? $pid : null;
    }


    /**
     * Returns the IDs of the nodes in the book outlines of the Book module in the order of the books.
     *
     * @return array<int, int> Node IDs ordered depth-first by weight and title like in Drupal
     */
    protected function bookOrder(): array
    {
        $children = [];

        foreach( $this->books() as $nid => $pid ) {
            $children[$pid][] = $nid;
        }

        $sort = fn( array $nids ) => Collection::make( $nids )->sortBy( [
            fn( $a, $b ) => $this->bookWeights[$a] <=> $this->bookWeights[$b],
            fn( $a, $b ) => strnatcasecmp( (string) ( $this->nodes->get( $a )->title ?? '' ), (string) ( $this->nodes->get( $b )->title ?? '' ) ),
        ] )->values()->all();

        $list = [];
        // the next node is at the end of the stack
        $stack = array_reverse( $sort( $children[0] ?? [] ) );

        while( ( $nid = array_pop( $stack ) ) !== null )
        {
            if( isset( $list[$nid] ) ) {
                continue;
            }

            $list[$nid] = $nid;
            array_push( $stack, ...array_reverse( $sort( $children[$nid] ?? [] ) ) );
        }

        return array_values( $list );
    }


    /**
     * Returns the parent node IDs of the nodes in the book outlines of the Book module.
     *
     * @return array<int, int> Parent node IDs by node ID, 0 for the top pages of the books
     */
    protected function books(): array
    {
        if( $this->books !== null ) {
            return $this->books;
        }

        $this->books = $this->bookWeights = [];

        // Drupal 8+ stores the parent in the "pid" column
        if( !$this->data->hasColumn( 'book', 'pid' ) ) {
            return $this->books;
        }

        foreach( $this->reader->books() as $row )
        {
            $nid = (int) $row->nid;
            $this->books[$nid] = $nid === (int) $row->bid ? 0 : (int) $row->pid;
            $this->bookWeights[$nid] = (int) $row->weight;
        }

        return $this->books;
    }
}

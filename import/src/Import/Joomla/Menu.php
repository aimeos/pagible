<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;

use Illuminate\Support\Collection;


/**
 * Menu items of the imported Joomla menu, their hierarchy and the articles imported by them.
 *
 * Which menu items get own pages depends on the categories too, so CategoryItems selects them.
 */
class Menu
{
    /** @var array<int|string, \stdClass> Menu items by ID, only looked up by ID */
    protected array $items = [];

    /** @var array<int|string, true> IDs of the articles imported by the public menu items as keys */
    protected array $articles = [];

    /** @var array<int, \stdClass> Menu items without parent item in the menu */
    protected array $roots = [];

    /** @var array<int, array<int, \stdClass>> Child menu items by parent item ID */
    protected array $children = [];


    /**
     * Builds the hierarchy of the menu items and selects the articles imported by them.
     *
     * @param Collection<int, \stdClass> $items Menu items ordered by their position in the menu
     * @param Access $access Checks if guests can see the menu items
     */
    public function __construct( Collection $items, Access $access )
    {
        $this->items = $items->keyBy( 'id' )->all();

        // the menu order is only needed while building the hierarchy
        $this->hierarchy( $items->values()->all() );
        $this->selectArticles( $access );
    }


    /**
     * Returns the menu item of the menu.
     *
     * @return \stdClass|null Menu item or NULL if it doesn't belong to the menu
     */
    public function item( int|string $id ): ?\stdClass
    {
        return $this->items[$id] ?? null;
    }


    /**
     * Tests if a public menu item of the menu imports the article below its parent page.
     *
     * Like the import of the menu items, restricted menu items leave their articles to the pages of their categories.
     */
    public function importsArticle( int|string $id ): bool
    {
        return isset( $this->articles[$id] );
    }


    /**
     * Returns the child menu items of the menu item.
     *
     * @param int|string $id ID of the menu item
     * @return array<int, \stdClass> Child menu items
     */
    public function children( int|string $id ): array
    {
        return $this->children[$id] ?? [];
    }


    /**
     * Returns the menu items in the order they are imported, each one directly followed by its descendants.
     *
     * @return \Generator<int, MenuNode> Menu items and the positions of their parent items by position
     */
    public function tree(): \Generator
    {
        $pos = 0;
        $stack = array_map( fn( $item ) => new MenuNode( $item ), array_reverse( $this->roots ) );

        while( $node = array_pop( $stack ) )
        {
            yield $pos => $node;

            foreach( array_reverse( $this->children( $node->item->id ) ) as $child ) {
                $stack[] = new MenuNode( $child, $pos );
            }

            $pos++;
        }
    }


    /**
     * Sorts the menu items into the root items and the child items of their parents.
     *
     * Menu items whose parent isn't in the menu are root items.
     *
     * @param array<int, \stdClass> $ordered Menu items ordered by their position in the menu
     */
    protected function hierarchy( array $ordered ): void
    {
        foreach( $ordered as $item )
        {
            $parentId = (int) ( $item->parent_id ?? Row::ROOT_ID );

            if( Row::isRootId( $parentId ) || !isset( $this->items[$parentId] ) ) {
                $this->roots[] = $item;
            } else {
                $this->children[$parentId][] = $item;
            }
        }
    }


    /**
     * Selects the articles imported by the public menu items linking to them.
     *
     * @param Access $access Checks if guests can see the menu items
     */
    protected function selectArticles( Access $access ): void
    {
        foreach( $this->items as $item )
        {
            if( ( $articleId = MenuItem::article( $item ) ) !== null && $access->restriction( $item ) === null ) {
                $this->articles[$articleId] = true;
            }
        }
    }
}

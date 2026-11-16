<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Illuminate\Support\Collection;


/**
 * Splits the Joomla articles or menu items into chunks whose texts are fetched at once.
 *
 * The texts can contain large HTML and embedded images, so the number of texts per chunk is limited
 * including the ones of the translations of the articles. The number of items per chunk is limited by
 * the same size, so chunks of items without article like headings aren't unbounded.
 */
class JoomlaChunks
{
    /**
     * Initializes the object.
     *
     * @param array<int|string, list<int|string>> $translations IDs of the translations by article ID
     * @param int $size Maximum number of article and translation texts and of items per chunk
     */
    public function __construct( protected array $translations, protected int $size )
    {
    }


    /**
     * Splits the articles into chunks whose texts including the ones of their translations are fetched at once.
     *
     * Articles whose texts exceed the size on their own are returned as single chunk.
     *
     * @template TKey of array-key
     * @template TValue
     * @param iterable<TKey, TValue> $items Values like category positions by article ID
     * @return \Generator<int, Collection<TKey, TValue>> Chunks of the items with their keys
     */
    public function articles( iterable $items ): \Generator
    {
        return $this->split( $items, fn( $item, $key ) => $this->textCount( $key ) );
    }


    /**
     * Splits the items like menu items into chunks whose texts of their articles and translations are fetched at once.
     *
     * Items without article have no texts, chunks of them are only limited by the number of items.
     *
     * @template TKey of array-key
     * @template TValue
     * @param iterable<TKey, TValue> $items Items like menu items
     * @param \Closure(TValue): (int|null) $articleId Returns the article ID of an item or NULL if it has no article
     * @return \Generator<int, Collection<TKey, TValue>> Chunks of the items with their keys
     */
    public function items( iterable $items, \Closure $articleId ): \Generator
    {
        return $this->split( $items, fn( $item ) => ( $id = $articleId( $item ) ) !== null ? $this->textCount( $id ) : 0 );
    }


    /**
     * Splits the items into chunks whose number of items and texts doesn't exceed the size unless an item exceeds it on its own.
     *
     * @template TKey of array-key
     * @template TValue
     * @param iterable<TKey, TValue> $items Items to split
     * @param \Closure(TValue, TKey): int $texts Returns the number of texts of an item
     * @return \Generator<int, Collection<TKey, TValue>> Chunks of the items with their keys
     */
    protected function split( iterable $items, \Closure $texts ): \Generator
    {
        $chunk = [];
        $count = 0;

        foreach( $items as $key => $item )
        {
            $num = $texts( $item, $key );

            if( $chunk && ( $count + $num > $this->size || count( $chunk ) >= $this->size ) )
            {
                yield Collection::make( $chunk );
                $chunk = [];
                $count = 0;
            }

            $chunk[$key] = $item;
            $count += $num;
        }

        if( $chunk ) {
            yield Collection::make( $chunk );
        }
    }


    /**
     * Returns the number of texts of the article including the ones of its translations.
     *
     * @param int|string $id Article ID
     */
    protected function textCount( int|string $id ): int
    {
        return 1 + count( $this->translations[$id] ?? [] );
    }
}

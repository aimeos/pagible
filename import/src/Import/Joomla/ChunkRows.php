<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * Articles and translations fetched for the chunk imported next, a new object is created by every prefetch.
 */
final readonly class ChunkRows
{
    /** @var array<int|string, bool> IDs of the pending articles of the chunk as keys, also if they don't exist any more */
    protected array $prefetched;


    /**
     * Initializes the object.
     *
     * @param array<int|string> $ids IDs of the pending articles of the chunk
     * @param array<int|string, \stdClass> $rows Articles of the chunk and their translations by ID
     */
    public function __construct( array $ids = [], protected array $rows = [] )
    {
        $this->prefetched = array_fill_keys( $ids, true );
    }


    /**
     * Returns the fetched article of the chunk.
     *
     * Pending articles are only imported from the chunk they were fetched for. Reading an article which wasn't fetched
     * would import it without its translations, texts and pages, so it's a programming error instead of a missing row.
     *
     * @param int|string $id ID of a pending article
     * @return \stdClass|null Article or NULL if it doesn't exist any more
     * @throws \LogicException If the article wasn't fetched for the chunk
     */
    public function article( int|string $id ): ?\stdClass
    {
        if( !isset( $this->prefetched[$id] ) ) {
            throw new \LogicException( sprintf( 'Article "%1$s" must be prefetched before', $id ) );
        }

        return $this->rows[$id] ?? null;
    }


    /**
     * Returns the fetched article or translation, also of articles which aren't pending.
     *
     * @return \stdClass|null Article or translation or NULL if it wasn't fetched
     */
    public function row( int|string $id ): ?\stdClass
    {
        return $this->rows[$id] ?? null;
    }


    /**
     * Returns the fetched articles or translations in the order of the IDs.
     *
     * @param array<int|string> $ids IDs of the articles or translations
     * @return list<\stdClass> Fetched rows, missing ones are skipped
     */
    public function pick( array $ids ): array
    {
        return Row::pick( $this->rows, $ids );
    }
}

<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Data and objects of a single Joomla import run, a new object is created for each run so nothing is reused if the command runs again.
 *
 * The imported articles and folders, the article of the root page and the objects building the pages are fixed for the run.
 * Only the pages used as parents grow while importing and the rows of the chunk imported next are replaced by every prefetch().
 */
final class JoomlaRun
{
    /** @var array<int|string, string> Pages packed by slim() the articles of the categories are imported below by category ID, also the root or fallback parent pages */
    private array $categoryPages = [];
    /** @var array<int|string, string> Pages of the articles imported by menu items packed by slim() by article ID */
    private array $articlePages = [];
    /** @var array<int|string, bool> IDs of the articles handled without page as keys, e.g. the articles whose import failed */
    private array $handled = [];
    /** Articles and translations of the chunk imported next, replaced by every prefetch() */
    private JoomlaChunkRows $chunk;
    /** @var array<int|string, bool> IDs of the articles of the chunk imported next whose pages are kept as imported before, replaced by every prefetch() */
    private array $kept = [];


    /**
     * Initializes the object.
     *
     * @param JoomlaReader $reader Reader for the articles of the chunks
     * @param JoomlaArticles $articles Imported articles with their categories, translations, menu items and URL paths
     * @param JoomlaFolders $folders Visible categories and the translations of the categories and menu items
     * @param JoomlaHomeRoot $home Home menu item of the site and the article imported into the root page
     * @param JoomlaUrls $urls Assigned URL paths of the pages
     * @param JoomlaLinks $links Resolves the Joomla links to the URL paths of the pages
     * @param JoomlaExtras $extras Fetches the tags and custom field values of the articles and categories
     * @param JoomlaTexts $texts Keeps the prefetched texts of the articles imported next
     * @param JoomlaContent $content Builds the content elements of the articles and categories
     * @param JoomlaPages $pages Builds the page data and the content of the pages
     */
    public function __construct(
        private readonly JoomlaReader $reader,
        public readonly JoomlaArticles $articles,
        public readonly JoomlaFolders $folders,
        public readonly JoomlaHomeRoot $home,
        public readonly JoomlaUrls $urls,
        public readonly JoomlaLinks $links,
        public readonly JoomlaExtras $extras,
        private readonly JoomlaTexts $texts,
        public readonly JoomlaContent $content,
        public readonly JoomlaPages $pages
    ) {
        $this->chunk = new JoomlaChunkRows();
    }


    /**
     * Remembers the page the articles of the category are imported below.
     *
     * @param int|string $catId ID of the category
     * @param string $page Page packed by slim()
     */
    public function addCategoryPage( int|string $catId, string $page ): void
    {
        $this->categoryPages[$catId] = $page;
    }


    /**
     * Returns the page the articles of the category are imported below.
     *
     * @param int|string $catId ID of the category
     * @return string|null Page packed by slim() or NULL if the category has no page yet
     */
    public function categoryPage( int|string $catId ): ?string
    {
        return $this->categoryPages[$catId] ?? null;
    }


    /**
     * Remembers the page of the article imported by a menu item.
     *
     * @param int|string $id ID of the article
     * @param string $page Page packed by slim()
     */
    public function addArticlePage( int|string $id, string $page ): void
    {
        $this->articlePages[$id] = $page;
    }


    /**
     * Returns the page of the article imported by a menu item.
     *
     * @param int|string $id ID of the article
     * @return string|null Page packed by slim() or NULL if the article wasn't imported by a menu item yet
     */
    public function articlePage( int|string $id ): ?string
    {
        return $this->articlePages[$id] ?? null;
    }


    /**
     * Remembers that the article was handled, so it isn't imported again even if it has no page.
     *
     * Used for the article of the root page and the other articles whose import failed, the failure is reported once
     * and the article isn't imported again by its category or other menu items.
     *
     * @param int|string $id ID of the article
     */
    public function markHandled( int|string $id ): void
    {
        $this->handled[$id] = true;
    }


    /**
     * Tests if the article was handled before without page.
     *
     * @param int|string $id ID of the article
     */
    public function handled( int|string $id ): bool
    {
        return isset( $this->handled[$id] );
    }


    /**
     * Fetches the pending articles imported next and their translations, replacing the ones of the previous chunk.
     *
     * The article of the root page and its translations are reused from JoomlaHomeRoot if they are within the IDs,
     * only the other articles are fetched.
     *
     * @param array<int|string> $ids IDs of the pending articles
     */
    public function prefetch( array $ids ): void
    {
        // the article of the root page and its translations were already fetched to check if it's imported into the root page
        $others = array_values( array_filter( $ids, fn( $id ) => !$this->home->isArticle( $id ) ) );
        $rows = count( $others ) < count( $ids ) ? $this->home->rows() ?? [] : [];
        $others = $rows !== [] ? $others : $ids;

        if( $others !== [] ) {
            $rows += $this->reader->articles( $this->withTranslations( $others ) );
        }

        $this->chunk = new JoomlaChunkRows( $ids, $rows );
        $this->kept = [];
    }


    /**
     * Remembers the articles of the chunk imported next whose pages are kept as imported before, so their texts aren't needed.
     *
     * The decision is made once for the chunk, so the import uses the same one the texts were fetched for.
     *
     * @param array<int|string> $ids IDs of the kept articles
     */
    public function keep( array $ids ): void
    {
        $this->kept = array_fill_keys( $ids, true );
    }


    /**
     * Tests if the page of the article of the chunk imported next is kept as imported before.
     *
     * @param int|string $id ID of the article
     */
    public function kept( int|string $id ): bool
    {
        return isset( $this->kept[$id] );
    }


    /**
     * Fetches the texts, tags and field values of the articles and translations imported next.
     *
     * @param array<int|string> $ids IDs of the articles and translations
     * @return array<int, string> Paths and URLs of the images in the texts whose files are imported
     */
    public function prefetchTexts( array $ids ): array
    {
        $this->texts->prefetch( $ids );
        $this->extras->prefetch( $ids );

        return $this->texts->imagePaths( $ids );
    }


    /**
     * Returns the pending article fetched for the current chunk.
     *
     * @param int|string $id ID of a pending article
     * @return \stdClass|null Article or NULL if it doesn't exist any more
     * @throws \LogicException If the article wasn't prefetched for the current chunk
     */
    public function article( int|string $id ): ?\stdClass
    {
        return $this->chunk->article( $id );
    }


    /**
     * Returns the article or translation fetched for the current chunk, also of articles which aren't pending.
     *
     * @param int|string $id ID of the article or translation
     * @return \stdClass|null Article or translation or NULL if it wasn't fetched
     */
    public function row( int|string $id ): ?\stdClass
    {
        return $this->chunk->row( $id );
    }


    /**
     * Returns the fetched translations of the article.
     *
     * @return list<\stdClass> Associated articles in other languages
     */
    public function translations( \stdClass $article ): array
    {
        return $this->chunk->pick( $this->articles->translations[$article->id] ?? [] );
    }


    /**
     * Returns the IDs of the articles, each followed by the IDs of its translations.
     *
     * @param array<int|string> $ids Article IDs
     * @return list<int|string> Article and translation IDs
     */
    public function withTranslations( array $ids ): array
    {
        return array_merge( [], ...array_map( fn( $id ) => [$id, ...$this->articles->translations[$id] ?? []], $ids ) );
    }


    /**
     * Returns the associated categories in other languages.
     *
     * @return list<\stdClass> Categories in other languages
     */
    public function categoryTranslations( \stdClass $category ): array
    {
        return $this->folders->categoryTranslations[$category->id] ?? [];
    }


    /**
     * Returns the associated menu items in other languages.
     *
     * @return list<\stdClass> Menu items in other languages
     */
    public function menuTranslations( \stdClass $menuItem ): array
    {
        return $this->folders->menuTranslations[$menuItem->id] ?? [];
    }


    /**
     * Returns the articles Joomla shows, the ones which weren't fetched for the current chunk are fetched at once.
     *
     * @param array<int|string> $ids IDs of the articles
     * @return array<int|string, \stdClass> Visible articles by ID
     */
    public function visibleArticles( array $ids ): array
    {
        $articles = $missing = [];

        foreach( $ids as $id )
        {
            if( !isset( $this->articles->visible[$id] ) ) {
                continue;
            }

            if( $row = $this->chunk->row( $id ) ) {
                $articles[$id] = $row;
            } else {
                $missing[$id] = $id;
            }
        }

        return $articles + ( $missing ? $this->reader->articles( array_values( $missing ) ) : [] );
    }
}

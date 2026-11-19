<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Joomla;

use Aimeos\Cms\DB;
use Aimeos\Cms\Import\Joomla;
use Aimeos\Cms\Import\Pages;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\PageView;


/**
 * Fetches the Joomla articles of the next chunk and keeps the pages of the ones which weren't changed since the date of the --since option.
 *
 * Requires the Shared trait used by the same class for the objects of the run and the shared methods.
 */
trait Changes
{
    /** @var array<string, list<string>> IDs of the variants using the domains and URL paths of the missing or moved variants of the current chunk by urlKey() */
    private array $usedTranslationUrls = [];


    /**
     * Fetches the articles and their translations, their texts, the pages of the articles and their variants at once.
     *
     * The fetched rows replace the ones of the previous chunk. The pages are fetched before the texts because
     * only the unchanged articles with a page of a previous import and unchanged names don't need their texts.
     *
     * @param array<int|string> $ids IDs of the articles imported next
     * @param PageView|null $root Root page if the article of the home menu item is imported next, the texts of the
     *  articles of the home menu items of the other languages are fetched too because the variants of the root page show them
     */
    protected function prefetch( array $ids, ?PageView $root = null ): void
    {
        // the texts of kept articles aren't needed
        $rendered = $this->prefetchArticles( $ids );
        $homes = $root ? $this->otherHomeArticles() : [];

        $this->prefetchTexts( array_values( array_unique( [...$this->run->withTranslations( $rendered ), ...$homes] ) ) );
    }


    /**
     * Fetches the pending articles and their translations and pages and decides which of them are kept, see prefetchKept().
     *
     * Used by the import and the dry run, so both make the same decisions. Only the import fetches the texts afterwards.
     * The steps depend on each other and are only done here in this order: run->prefetch() replaces the rows of the
     * previous chunk and forgets its kept articles, prefetchKept() needs the fetched rows for the URL paths and translations
     * of the articles and run->keep() stores its decision for importArticle() and the dry run until the next chunk.
     *
     * @param array<int|string> $ids IDs of the articles imported next
     * @return list<int|string> IDs of the pending articles which are rendered, i.e. not kept
     */
    protected function prefetchArticles( array $ids ): array
    {
        // the translations are needed by saveTranslations() if the article is rendered
        $ids = array_values( array_filter( $ids, $this->pending( ... ) ) );
        $this->run->prefetch( $ids );
        $this->run->keep( $kept = $this->prefetchKept( $ids ) );

        return array_values( array_diff( $ids, $kept ) );
    }


    /**
     * Fetches the pages and variants of the fetched articles and keeps the pages of the unchanged ones, see keepsArticle().
     *
     * Decides once per chunk which articles aren't rendered again, see prefetchArticles(). The pages
     * of the articles, the variants of the pages with translations and the URLs of the missing or moved variants used
     * by other pages are fetched at once before, the variants are needed to compare the names and URL paths of the
     * translations and by saveTranslations() for the rendered articles. The article of the root page is always rendered
     * because the pages don't store which article the root page shows, the root page only gets a new version if its
     * content changed. Its own URL path isn't used then.
     *
     * @param array<int|string> $ids IDs of the fetched articles
     * @return list<int|string> IDs of the kept articles
     */
    protected function prefetchKept( array $ids ): array
    {
        $slugs = $this->articleSlugs( array_filter( $ids, fn( $id ) => !$this->run->home->isArticle( $id ) ) );

        $this->prefetchPages( array_values( $slugs ) );
        $this->prefetchArticleVariants( $slugs );

        $pages = [];

        foreach( $slugs as $id => $slug )
        {
            if( $page = $this->prefetchedPages[trim( $slug, '/' )] ?? null ) {
                $pages[$id] = $page;
            }
        }

        // without --since, all articles are rendered again, so the keep checks and their queries aren't needed
        if( $this->since === null ) {
            return [];
        }

        $this->prefetchTranslationUrls( $pages );
        $kept = [];

        foreach( $pages as $id => $page )
        {
            if( ( $article = $this->run->row( $id ) ) && $this->keepsArticle( $article, $page ) ) {
                $kept[] = $id;
            }
        }

        return $kept;
    }


    /**
     * Returns the IDs of the articles Joomla shows for the home menu items of the other languages.
     *
     * @return list<int> Article IDs
     */
    protected function otherHomeArticles(): array
    {
        $ids = array_map( Joomla\MenuItem::article( ... ), $this->wiring->homes->otherHomes() );
        return array_values( array_filter( $ids, fn( $id ) => $id !== null && isset( $this->run->articles->visible[$id] ) ) );
    }


    /**
     * Returns the slugs of the pages of the fetched articles.
     *
     * @param array<int|string> $ids IDs of the articles
     * @return array<int|string, string> Slugs by article ID
     */
    protected function articleSlugs( array $ids ): array
    {
        $slugs = [];

        foreach( $ids as $id )
        {
            if( $article = $this->run->row( $id ) ) {
                $slugs[$id] = $this->run->urls->articlePath( $article );
            }
        }

        return $slugs;
    }


    /**
     * Fetches the variants of the existing pages of the articles with translations at once.
     *
     * The variants are compared with the names and URL paths of the translations by keepsArticle() and needed by saveTranslations()
     * for the rendered articles.
     *
     * @param array<int|string, string> $slugs Slugs of the pages of the articles by article ID
     */
    protected function prefetchArticleVariants( array $slugs ): void
    {
        $pageIds = [];

        foreach( $slugs as $id => $slug )
        {
            if( empty( $this->run->articles->translations[$id] ) ) {
                continue;
            }

            if( $page = $this->prefetchedPages[trim( $slug, '/' )] ?? null ) {
                $pageIds[] = (string) $page->id;
            }
        }

        $this->prefetchVariants( $pageIds );
    }


    /**
     * Fetches the texts, tags and field values of the articles and the files of their images at once.
     *
     * @param array<int|string> $ids IDs of the articles and translations
     */
    protected function prefetchTexts( array $ids ): void
    {
        $this->prefetchFiles( array_map( $this->resolveFile( ... ), $this->run->prefetchTexts( $ids ) ) );
    }


    /**
     * Tests if the article is imported and has no page yet.
     *
     * Articles handled without page, e.g. the article of the root page or other articles whose import failed, aren't pending.
     */
    protected function pending( int|string $id ): bool
    {
        return isset( $this->run->articles->categoryIds[$id] ) && !$this->createdPages->has( $id ) && !$this->run->handled( $id );
    }


    /**
     * Returns the fetched article if it's imported and has no page yet.
     *
     * @return \stdClass|null Article or NULL if it isn't imported, has a page already or doesn't exist any more
     * @throws \LogicException If the pending article wasn't prefetched for the current chunk
     */
    protected function pendingArticle( int|string $id ): ?\stdClass
    {
        return $this->pending( $id ) ? $this->run->article( $id ) : null;
    }


    /**
     * Tests if the page of a previous import is kept for the article which wasn't changed since the --since date.
     *
     * Joomla menu items have no modification time, so their changes can't be found by the --since date. The pages
     * of unchanged articles are imported again if the name of the page or of one of its variants from the menu items
     * linking to the article or its translations changed. Disabled pages are always imported again because Joomla doesn't
     * record publishing an article from the article list as modification, so pages disabled because their article wasn't
     * imported any more are enabled again, see disableVanished().
     *
     * @param \stdClass $article Joomla article
     * @param PageView|null $page Page of a previous import or NULL if there's none
     */
    protected function keepsArticle( \stdClass $article, ?PageView $page ): bool
    {
        // disabled pages are rendered again, e.g. pages disabled by disableVanished() whose article was published again from the article list
        return $page !== null && (int) $page->status !== Pages::STATUS_DISABLED && $this->unchangedSince( $article->id )
            && $page->name === $this->run->pages->articleName( $article ) && $this->keepsTranslations( $article, $page );
    }


    /**
     * Tests if the variants of the page of a previous import still have the names and URL paths of the translations of the article.
     *
     * Translations are named by their own menu items, which can be renamed without changing the translations, and their
     * URL paths also change with the paths of their categories. Variants changed in Pagible aren't overwritten, so their
     * names and paths don't matter. Missing or moved variants are saved by rendering the article unless their URL path
     * is used by another page, saveVariant() would skip them in every run then. Variants of the import trashed in
     * Pagible are restored by rendering the article like Variants::save() does if they are imported again.
     *
     * @param \stdClass $article Joomla article
     * @param PageView $page Page of a previous import
     */
    protected function keepsTranslations( \stdClass $article, PageView $page ): bool
    {
        if( ( $rows = $this->run->translations( $article ) ) === [] ) {
            return true;
        }

        // prefetched for the whole chunk by prefetchArticleVariants() and only used up by variants() if the article is rendered
        $variants = $this->prefetchedVariants( $page ) ?? $this->variants( $page );

        foreach( $this->translationTargets( $rows, $page, $variants ) as ['row' => $row, 'variant' => $variant, 'domain' => $domain, 'path' => $path, 'moved' => $moved] )
        {
            // translations changed in Pagible are skipped by saveTranslation() anyway
            if( $variant && $variant->editor !== $this->editor ) {
                continue;
            }

            // like Variants::save(), only missing and moved variants can't be saved if their URL is used by another page
            if( $moved || !$variant )
            {
                if( !$this->usedTranslationUrl( $domain, $path, $variant ) ) {
                    return false;
                }

                continue;
            }

            // trashed variants are restored by Variants::save() and renamed variants get the new name
            if( $variant->getAttribute( 'variant_deleted_at' ) !== null || $variant->name !== $this->run->pages->articleName( $row ) ) {
                return false;
            }
        }

        return true;
    }


    /**
     * Fetches which URLs of the missing or moved variants of the pages of the articles are used by other pages at once.
     *
     * The variants of the pages must be prefetched before, the found URLs replace the ones of the previous chunk.
     * The database compares the domains and paths like Variants::save(), e.g. case-insensitive with MySQL collations,
     * so the found rows are assigned to the requested URLs by urlKey() instead of their exact values.
     *
     * @param array<int|string, PageView> $pages Pages of a previous import by article ID
     */
    protected function prefetchTranslationUrls( array $pages ): void
    {
        $this->usedTranslationUrls = [];
        $domains = [];
        $urls = [];
        $keys = [];

        foreach( $pages as $id => $page )
        {
            if( empty( $this->run->articles->translations[$id] ) || !( $article = $this->run->row( $id ) ) ) {
                continue;
            }

            $variants = $this->prefetchedVariants( $page ) ?? [];

            foreach( $this->translationTargets( $this->run->translations( $article ), $page, $variants ) as $target )
            {
                if( $target['moved'] )
                {
                    $urls[$target['path']] = true;
                    $domains[$target['domain']] = true;
                    $keys[$this->urlKey( $target['domain'], $target['path'] )] = true;
                }
            }
        }

        if( $keys === [] ) {
            return;
        }

        foreach( array_chunk( array_map( 'strval', array_keys( $urls ) ), DB::IN_SIZE ) as $chunk )
        {
            $query = PageVariant::withTrashed()->whereIn( 'path', $chunk )->whereIn( 'domain', array_map( 'strval', array_keys( $domains ) ) );

            // paths and domains are matched separately by the database, so only the requested combinations are used
            foreach( $query->get( ['id', 'domain', 'path'] ) as $used )
            {
                if( isset( $keys[$key = $this->urlKey( (string) $used->domain, (string) $used->path )] ) ) {
                    $this->usedTranslationUrls[$key][] = (string) $used->id;
                }
            }
        }
    }


    /**
     * Returns the translations of the article in imported languages together with their variants and URLs.
     *
     * Used by keepsTranslations() and prefetchTranslationUrls(), so both compare the variants with the same URLs.
     * Translations in languages which aren't imported have no variant and are left out.
     *
     * @param list<\stdClass> $rows Translations of the article
     * @param PageView $page Page of the article in the import language
     * @param array<string, PageView> $variants Variants of the page by language code
     * @return list<array{row: \stdClass, variant: PageView|null, domain: string, path: string, moved: bool}> Translations with
     *  their existing variant, the domain and URL path the variant is saved with and if the variant is missing or has another URL
     */
    protected function translationTargets( array $rows, PageView $page, array $variants ): array
    {
        $languages = $this->wiring->site->languages;
        $list = [];

        foreach( $rows as $row )
        {
            if( !( $language = $languages->language( Joomla\Row::language( $row ) ) ) ) {
                continue;
            }

            $variant = $variants[$language['lang']] ?? null;
            ['domain' => $domain, 'path' => $path] = $this->translationUrl( $row, $language, $page );
            $moved = !$variant || (string) $variant->domain !== $domain || (string) $variant->path !== $path;

            $list[] = ['row' => $row, 'variant' => $variant, 'domain' => $domain, 'path' => $path, 'moved' => $moved];
        }

        return $list;
    }


    /**
     * Tests if the URL of the missing or moved variant is used by another page, see prefetchTranslationUrls().
     *
     * Like Variants::save(), the variant itself doesn't use the URL, e.g. if only the case of its path changed.
     *
     * @param string $domain Domain of the translation
     * @param string $path URL path of the translation without slashes
     * @param PageView|null $variant Existing variant of the page in the language of the translation or NULL if there's none
     */
    protected function usedTranslationUrl( string $domain, string $path, ?PageView $variant ): bool
    {
        $own = $variant ? (string) $variant->variant_id : null;

        foreach( $this->usedTranslationUrls[$this->urlKey( $domain, $path )] ?? [] as $id )
        {
            if( $id !== $own ) {
                return true;
            }
        }

        return false;
    }


    /**
     * Returns the key the domain and URL path of a variant are compared by.
     *
     * Databases with case- and accent-insensitive collations like MySQL consider domains and paths differing only in
     * case or accents as the same, so the found rows must be assigned to the requested URLs the same way, see
     * Joomla\Claims::key(). With case-sensitive databases, the rows found are equal anyway.
     *
     * @param string $domain Domain of the variant
     * @param string $path URL path of the variant without slashes
     */
    protected function urlKey( string $domain, string $path ): string
    {
        return Joomla\Claims::key( $domain . "\n" . $path );
    }


    /**
     * Returns the domain and URL path the variant of the article translation is saved with, see Variants::save().
     *
     * @param \stdClass $row Translated article
     * @param array{lang: string, domain: string} $language Language of the translation
     * @param PageView $page Page of the article in the import language
     * @return array{domain: string, path: string} Domain and URL path without slashes
     */
    protected function translationUrl( \stdClass $row, array $language, PageView $page ): array
    {
        return [
            'domain' => $language['domain'] !== '' ? $language['domain'] : (string) $page->domain,
            'path' => trim( $this->run->urls->articleTranslationPath( $row ), '/' ),
        ];
    }
}

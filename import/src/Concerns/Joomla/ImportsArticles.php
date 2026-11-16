<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Joomla;

use Aimeos\Cms\DB;
use Aimeos\Cms\Import\Joomla;
use Aimeos\Cms\Import\Pages;
use Aimeos\Cms\Import\Status;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\PageView;


/**
 * Imports the Joomla articles in chunks below the pages of their categories.
 *
 * Requires the Imports trait used by the same class for the objects of the run and the shared methods.
 */
trait ImportsArticles
{
    /** @var array<string, list<string>> IDs of the variants using the domains and URL paths of the missing or moved variants of the current chunk by urlKey() */
    private array $usedTranslationUrls = [];


    /**
     * Imports a single article as a Pagible page or updates the page of a previous import and logs the result.
     *
     * @return PageView Created, updated or unchanged page
     */
    protected function importArticle( \stdClass $article, PageView $parent ): PageView
    {
        $run = $this->run;
        $slug = $run->urls->articlePath( $article );
        $title = Joomla\Row::title( $article );

        // articles whose translations and menu item names also weren't changed since the given date aren't rendered again,
        // which was decided once by prefetch() because their texts weren't fetched then
        if( $run->kept( $article->id ) && ( $page = $this->previousPage( $slug ) ) )
        {
            $this->moveBelow( $page, $parent );
            // the translations and variants were fetched for the whole chunk by prefetchKept(), pages without translations have no variants
            $this->formerTranslations( $page, $run->translations( $article ), $this->prefetchedVariants( $page ) ?? [], "{$title} (/{$slug})" );
            return $this->imported( $article->id, $page, $title, $slug, Status::Unchanged );
        }

        $rendered = $run->pages->articlePage( $article, $slug, $this->wiring->router->contentRoutes( Joomla\Row::language( $article ) ) );
        $created = Joomla\Date::first( $article->created ?? null );
        ['page' => $page, 'status' => $status] = $this->savePage( $slug, $rendered->data, $rendered->content, $parent, $created );
        $this->notice( $rendered->notice(), "{$title} (/{$slug})", $rendered->reason );

        // teasers are published, so their translations are published too
        $translations = $run->translations( $article );
        $variants = $this->savedVariants( $page, $status, $translations );
        $this->saveTranslations( $page, $translations, Joomla\Kind::ARTICLE, $variants, !$rendered->teaser && $rendered->reason !== null );
        $this->formerTranslations( $page, $translations, $variants, "{$title} (/{$slug})" );

        return $this->imported( $article->id, $page, $title, $slug, $status );
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
            && $page->name === $this->articleName( $article ) && $this->keepsTranslations( $article, $page );
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
        $languages = $this->wiring->site->languages;

        foreach( $rows as $row )
        {
            // translations in languages which aren't imported have no variant
            if( !( $language = $languages->language( Joomla\Row::language( $row ) ) ) ) {
                continue;
            }

            $variant = $variants[$language['lang']] ?? null;

            // translations changed in Pagible are skipped by saveTranslation() anyway
            if( $variant && $variant->editor !== $this->editor ) {
                continue;
            }

            ['domain' => $domain, 'path' => $path] = $this->translationUrl( $row, $language, $page );

            // like Variants::save(), only missing and moved variants can't be saved if their URL is used by another page
            if( !$variant || (string) $variant->domain !== $domain || (string) $variant->path !== $path )
            {
                if( !$this->usedTranslationUrl( $domain, $path, $variant ) ) {
                    return false;
                }

                continue;
            }

            // trashed variants are restored by Variants::save() and renamed variants get the new name
            if( $variant->getAttribute( 'variant_deleted_at' ) !== null || $variant->name !== $this->articleName( $row ) ) {
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
        $languages = $this->wiring->site->languages;
        $domains = [];
        $urls = [];
        $keys = [];

        foreach( $pages as $id => $page )
        {
            if( empty( $this->run->articles->translations[$id] ) || !( $article = $this->run->row( $id ) ) ) {
                continue;
            }

            $variants = $this->prefetchedVariants( $page ) ?? [];

            foreach( $this->run->translations( $article ) as $row )
            {
                if( !( $language = $languages->language( Joomla\Row::language( $row ) ) ) ) {
                    continue;
                }

                $variant = $variants[$language['lang']] ?? null;
                $url = $this->translationUrl( $row, $language, $page );

                if( !$variant || (string) $variant->domain !== $url['domain'] || (string) $variant->path !== $url['path'] )
                {
                    $urls[$url['path']] = true;
                    $domains[$url['domain']] = true;
                    $keys[$this->urlKey( $url['domain'], $url['path'] )] = true;
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
     * case or accents as the same, so the found rows must be assigned to the requested URLs the same way. Without the
     * "intl" extension, only the case is ignored. With case-sensitive databases, the rows found are equal anyway.
     *
     * @param string $domain Domain of the variant
     * @param string $path URL path of the variant without slashes
     */
    protected function urlKey( string $domain, string $path ): string
    {
        $key = mb_strtolower( $domain . "\n" . $path );

        if( class_exists( \Normalizer::class ) && ( $decomposed = \Normalizer::normalize( $key, \Normalizer::FORM_D ) ) !== false ) {
            $key = (string) preg_replace( '/\p{Mn}+/u', '', $decomposed );
        }

        return $key;
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


    /**
     * Returns the name of the page of the article or translation, the same one articlePage() builds the page with.
     *
     * @param \stdClass $article Joomla article or translation
     */
    protected function articleName( \stdClass $article ): string
    {
        return $this->run->pages->articleName( $article );
    }


    /**
     * Returns the page of the category and creates it including its parent categories if necessary.
     *
     * Top-level categories have no page unless a menu item links to them.
     */
    protected function parentForCategory( int $catId, PageView $root ): PageView
    {
        $run = $this->run;

        if( ( $id = $run->categoryPage( $catId ) ) !== null ) {
            return $this->slimPage( $id );
        }

        $categoryItems = $this->wiring->categoryItems;

        // articles with own menu item in categories with hidden parents are shown by Joomla 4+ but their categories aren't
        if( !( $category = $categoryItems->category( $catId ) ) ) {
            return $root;
        }

        $parent = $category->parent_id ? $this->parentForCategory( (int) $category->parent_id, $root ) : $root;
        $routes = $this->wiring->router->contentRoutes( Joomla\Row::language( $category ) );
        // category() already ruled out the menu items of the imported menu, so the own menu item of the category is one of another menu
        $other = $categoryItems->otherItem( $category );
        // the articles are imported below the parent if the page of the category can't be saved, the error is reported once
        $page = $this->transaction( 'category ' . Joomla\Row::label( $category ),
            fn() => $this->createCategoryFolder( $category, $routes, $other, $parent ) ) ?? $parent;
        $run->addCategoryPage( $catId, $this->slim( $page ) );

        return $page;
    }


    /**
     * Creates the page of a category without menu item or updates the one of a previous import.
     *
     * Category pages contain the description and the image of the category like Joomla shows them above the articles.
     *
     * @param Joomla\Routes $routes Routes of the language of the category
     * @param \stdClass|null $item Menu item of another menu linking to the category or NULL if there's none
     * @return PageView Saved page
     */
    protected function createCategoryFolder( \stdClass $category, Joomla\Routes $routes, ?\stdClass $item, PageView $parent ): PageView
    {
        $slug = $this->run->urls->categoryPath( $category );
        $rendered = $this->run->pages->categoryPage( $category, $slug, $routes, $item );
        $saved = $this->savePage( $slug, $rendered->data, $rendered->content, $parent );

        return $this->savedPage( $saved, $category, Joomla\Kind::CATEGORY, $slug, $rendered->reason );
    }
}

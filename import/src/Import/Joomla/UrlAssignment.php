<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;

use Illuminate\Support\Arr;


/**
 * Claims the URL paths of the imported Joomla menu items, categories, articles, translations and redirects at once.
 *
 * Pagible uses a URL path within the domain only once, so the rows claiming a used path get their ID appended.
 * Like the Joomla router, the URL paths of the menu items and the ones Joomla uses are claimed first, the paths
 * are claimed in this order:
 *
 * 1. home pages of the imported pages and of the languages of the root page variants, e.g. "" and "fr"
 * 2. menu items of the imported menu and the articles with the URL paths of their menu items of any menu
 * 3. categories with a Joomla route
 * 4. other articles with a Joomla route
 * 5. translated menu items in the domains of their languages
 * 6. translated articles with the URL paths of their menu items in the domains of their languages
 * 7. translated categories in the domains of their languages
 * 8. old URL paths of the imported redirects, which are skipped if the path is used already
 * 9. articles without a Joomla route by their alias
 * 10. categories without a Joomla route by their alias
 * 11. other translated articles in the domains of their languages
 *
 * Within the domain of each language, the translated menu items and the translated articles with the URL paths of their
 * menu items come before the translated categories like the menu items of the imported menu and the articles of the
 * menu items come before the categories. The translated categories claim the routes of their languages before the
 * redirects like the categories with a Joomla route do. The other translated articles are claimed last because their
 * paths are stored without the difference between a Joomla route and the alias Joomla falls back to, see
 * TranslationRoute and Index::routes(), so they are claimed like aliases: in the domain of the imported pages,
 * the redirects and the aliases of the articles and categories of the imported language keep their paths and the
 * translations get their ID appended instead. Only the categories and translations which get own pages
 * claim URL paths, which are the categories with imported articles below them, see CategoryItems::pages().
 * The article imported into the root page and its translations use the home pages, so they claim no URL paths.
 */
final class UrlAssignment
{
    /**
     * Initializes the object.
     *
     * @param UrlContext $context Domains of the languages of the translations, routes and menu items the URL paths are built from
     * @param Origin $origin Recognizes the old URLs of the redirects
     * @param \Closure(AssignedUrls): Links $linker Returns the links resolving the URLs of the assigned URL paths,
     *  used to keep only the redirects whose targets are supported, see Links::redirectTarget()
     */
    public function __construct(
        private UrlContext $context,
        private Origin $origin,
        private \Closure $linker
    ) {
    }


    /**
     * Claims the URL paths of the menu items, categories, articles, translations and redirects.
     *
     * The article imported into the root page and its translations use the home pages, the category shown on the root page
     * and its translations use them too.
     *
     * @param Articles $articles Imported articles with the URL paths Joomla uses for them and their translations
     * @param Folders $folders Visible categories which aren't translations and the translated categories and menu items grouped by ID
     * @param HomeRoot $home Home menu item, the article imported into the root page and its translations
     * @param iterable<\stdClass> $redirects Joomla redirects with their old and new URLs
     * @param array<string, \stdClass> $otherHomes Home menu items of the other languages by language tag, see Homes::otherHomes()
     * @return AssignedUrls Assigned URL paths and the kept redirects
     */
    public function assign( Articles $articles, Folders $folders, HomeRoot $home, iterable $redirects,
        array $otherHomes = [] ): AssignedUrls
    {
        $categoryItems = $this->context->categoryItems;
        $homeCatId = $categoryItems->homeCatId();
        // only the categories whose pages are created when their articles are imported claim URL paths
        $pages = $categoryItems->pages( $articles->categoryIds, $home->articleId );
        // the article imported into the root page and its translations use the home pages instead of own ones
        $rootIds = $home->articleId !== null ? [$home->articleId => true, ...array_fill_keys( $articles->translations[$home->articleId] ?? [], true )] : [];
        $articleRoutes = array_diff_key( $articles->routes[Kind::Article->value], $rootIds );
        $menuPages = $categoryItems->menuPages();

        // the claimed URL paths are only needed while assigning them
        $claims = new Claims();
        $this->reserveHomes( $claims, $folders, $home, $homeCatId, $otherHomes, $articles->visible );

        foreach( $menuPages as $id => $item ) {
            $claims->claim( UrlClaim::row( Kind::Menu, $id, $this->context->paths->itemPath( $item ) ) );
        }

        // like the Joomla router, the articles whose URL paths are the ones of their menu items come before the categories
        $menuArticles = array_intersect_key( $articleRoutes, $this->menuRouted( $articles, $articleRoutes ) );
        $deferred = $this->claimRoutes( $claims, Kind::Article, $menuArticles );

        $categoryRoutes = array_map( $this->context->paths->categoryRoute( ... ), array_intersect_key( $folders->categories, $pages ) );
        $categories = $this->claimRoutes( $claims, Kind::Category, $categoryRoutes );
        $deferred = [...$deferred, ...$this->claimRoutes( $claims, Kind::Article, array_diff_key( $articleRoutes, $menuArticles ) )];

        $menuRows = $this->pagedTranslations( Arr::flatten( array_intersect_key( $folders->menuTranslations, $menuPages ), 1 ) );
        $menuKeys = $this->claimTranslations( $claims, Kind::Menu, $menuRows, $this->context->paths->menuTranslationRoute( ... ) );

        // like the articles, the translated articles with the URL paths of their menu items come before the translated categories
        $translations = $this->articleTranslations( array_diff_key( $articles->routes[Kind::ARTICLE_TRANSLATION], $rootIds ) );
        $menuTranslations = array_intersect_key( $translations, $this->menuRouted( $articles, $translations ) );

        foreach( $menuTranslations as $claim ) {
            $claims->claim( $claim );
        }

        $categoryRows = Arr::flatten( array_intersect_key( $folders->categoryTranslations, $pages ), 1 );
        $categoryKeys = $this->claimTranslations( $claims, Kind::Category, $categoryRows, $this->context->paths->categoryTranslationRoute( ... ) );

        // the URL paths are assigned without redirects first to resolve their targets, see importedRedirects()
        $keys = new AssignedUrls(
            articleKeys: $this->articleKeys( $translations, $articleRoutes ),
            menuKeys: $menuKeys,
            categoryKeys: $categoryKeys + $this->menuCategoryKeys( $folders, $menuKeys, $homeCatId ),
            homes: $home->articleId !== null ? $this->homes( $home->articleId, $home->translations, $otherHomes ) : [],
            translatedCategories: array_fill_keys( array_column( Arr::flatten( $folders->categoryTranslations, 1 ), 'id' ), true ),
            categoryHomes: $home->item && $homeCatId !== null ? $this->categoryHomes( $folders, $home->item, $homeCatId, $otherHomes ) : []
        );

        $later = [...$deferred, ...$categories, ...array_diff_key( $translations, $menuTranslations )];
        return $this->claimRedirects( $claims, $redirects, $later, $keys );
    }


    /**
     * Claims the old URL paths of the imported redirects and afterwards the paths of the rows claimed after them.
     *
     * @param Claims $claims URL paths claimed before the redirects
     * @param iterable<\stdClass> $redirects Joomla redirects with their old and new URLs
     * @param array<int|string, UrlClaim> $later Claims of the rows claiming their paths after the redirects
     * @param AssignedUrls $keys Keys and home pages of the rows without the claimed URL paths
     * @return AssignedUrls Assigned URL paths and the kept redirects
     */
    private function claimRedirects( Claims $claims, iterable $redirects, array $later, AssignedUrls $keys ): AssignedUrls
    {
        [$imported, $unsupported] = $this->importedRedirects( $redirects, $claims, $later, $keys );
        $kept = $this->keepRedirects( $claims, $imported );

        foreach( $later as $claim ) {
            $claims->claim( $claim );
        }

        // like the imported redirects, the unsupported ones whose old URL paths are used by pages or kept redirects are skipped
        // silently, also the paths claimed after the redirects, so they are checked against the final claims without changing them
        $unsupported = $this->keepRedirects( clone $claims, $unsupported );

        return $keys->withClaims( $claims, $kept, $unsupported );
    }


    /**
     * Returns the record keys and language domains of the imported articles and translated articles.
     *
     * @param array<int|string, UrlClaim> $translations Claims of the translated articles by ID
     * @param array<int|string, RoutePath> $articleRoutes URL paths of the articles by ID
     * @return array<int|string, UrlKey> Keys by article ID, the IDs of the articles and their translations don't overlap
     */
    private function articleKeys( array $translations, array $articleRoutes ): array
    {
        $keys = array_map( fn( UrlClaim $claim ) => $claim->urlKey, $translations );

        foreach( array_keys( $articleRoutes ) as $id ) {
            $keys[$id] = new UrlKey( Kind::Article->key( $id ) );
        }

        return $keys;
    }


    /**
     * Reserves the URL paths of the root page and of its variants in the languages on the domain of the imported pages.
     *
     * The variants of the root page use the language code as URL path, e.g. "fr", so rows with the same alias get their ID appended.
     * Like the import of the home menu item, the variants are the translations of the article imported into the root page or the
     * translated home menu items if the home menu item shows no article. With language filter, the home menu items of the other
     * languages are root page variants instead of the translations in their languages, except the ones whose article Joomla
     * doesn't show like Home::saveHomeTranslations() skips them. The root page gets no variants if the home menu
     * item is imported like the other ones.
     *
     * @param Claims $claims URL paths claimed during the assignment
     * @param Folders $folders Translated menu items grouped by ID, the ones of the home menu item are variants of the root page
     * @param HomeRoot $home Home menu item of the site and the translations of the article imported into the root page
     * @param int|null $homeCatId ID of the category shown on the root page or NULL if there's none
     * @param array<string, \stdClass> $otherHomes Home menu items of the other languages by language tag
     * @param array<int|string, bool> $visible IDs of the articles Joomla shows as keys
     */
    private function reserveHomes( Claims $claims, Folders $folders, HomeRoot $home, ?int $homeCatId,
        array $otherHomes, array $visible ): void
    {
        $claims->claimPath( '' );

        if( !$home->item ) {
            return;
        }

        $link = MenuLink::of( $home->item );

        $rows = match( true ) {
            $link->article() !== null => $home->articleId !== null ? $home->translations : null,
            $link->kind === MenuLink::KIND_CATEGORY && $link->id && $homeCatId === null => null,
            default => $folders->menuTranslations[$home->item->id] ?? [],
        };

        // the home menu item is imported like the other ones, so the root page gets no variants
        if( $rows === null ) {
            return;
        }

        $tags = [];

        // the home menu items of the other languages replace the translations in their languages, see homeShows()
        foreach( $rows as $row )
        {
            if( !isset( $otherHomes[$tag = Row::language( $row )] ) ) {
                $tags[$tag] = $tag;
            }
        }

        foreach( $otherHomes as $tag => $item )
        {
            if( ( $id = MenuItem::article( $item ) ) === null || isset( $visible[$id] ) ) {
                $tags[$tag] = $tag;
            }
        }

        foreach( $tags as $tag )
        {
            if( ( $path = $this->context->domains->tagRootPath( $tag ) ) !== null ) {
                $claims->claimPath( $path, $this->context->domains->domain( $tag ) );
            }
        }
    }


    /**
     * Claims the URL paths Joomla uses for the articles or for the categories without menu item of the imported menu.
     *
     * Categories with own menu item use the page of the menu item, the ones without own page aren't passed.
     *
     * @param Claims $claims URL paths claimed during the assignment
     * @param Kind::Article|Kind::Category $kind Type of the rows
     * @param array<int|string, RoutePath> $routes URL paths Joomla uses or NULL and aliases of the rows by ID
     * @return list<UrlClaim> Aliases of the rows without URL path in Joomla, which are claimed later
     */
    private function claimRoutes( Claims $claims, Kind $kind, array $routes ): array
    {
        $deferred = [];

        foreach( $routes as $id => $route ) {
            $deferred[] = $this->claimOrDefer( $claims, $kind, $id, $route );
        }

        return array_values( array_filter( $deferred ) );
    }


    /**
     * Claims the URL path Joomla uses or returns the claim of the alias.
     *
     * @param Claims $claims URL paths claimed during the assignment
     * @param Kind::Article|Kind::Category $kind Type of the row
     * @param int|string $id ID of the row
     * @param RoutePath $route URL path Joomla uses or NULL if there's none and the alias of the row
     * @return UrlClaim|null Claim of the alias which is claimed later or NULL if the URL path Joomla uses is claimed
     */
    private function claimOrDefer( Claims $claims, Kind $kind, int|string $id, RoutePath $route ): ?UrlClaim
    {
        $claim = UrlClaim::row( $kind, $id, $route->path() );

        if( $route->route === null ) {
            return $claim;
        }

        $claims->claim( $claim );
        return null;
    }


    /**
     * Returns the keys of the translated menu items whose page variants show the translated categories.
     *
     * Categories shown by a menu item of the imported menu use its page, which only gets the translated menu items as
     * variants. Like links to the category use the page of the menu item, links to its translations use the variant of
     * the translated menu item in their language. Translations without translated menu item in their language get no key.
     * The translations of the category shown on the root page use the root page variants instead, see categoryHomes().
     *
     * @param Folders $folders Translated categories and menu items grouped by category and menu item ID
     * @param array<int|string, UrlKey> $menuKeys Record keys and language domains of the translated menu items with page variants by ID
     * @param int|null $homeCatId ID of the category shown on the root page or NULL if there's none
     * @return array<int|string, UrlKey> Keys of the page variants of the translated menu items by translated category ID
     */
    private function menuCategoryKeys( Folders $folders, array $menuKeys, ?int $homeCatId ): array
    {
        $keys = [];

        foreach( $folders->categoryTranslations as $catId => $rows )
        {
            if( (int) $catId === $homeCatId || !( $item = $this->context->categoryItems->categoryPageItem( $catId ) ) ) {
                continue;
            }

            $variants = [];

            foreach( $folders->menuTranslations[$item->id] ?? [] as $row )
            {
                if( isset( $menuKeys[$row->id] ) ) {
                    $variants[Row::language( $row )] ??= $menuKeys[$row->id];
                }
            }

            foreach( $rows as $row )
            {
                if( $key = $variants[Row::language( $row )] ?? null ) {
                    $keys[$row->id] = $key;
                }
            }
        }

        return $keys;
    }


    /**
     * Claims the URL paths of the translated categories or menu items in the domains of their languages.
     *
     * Only the translations of the categories and menu items with own pages are imported as page variants.
     *
     * @param Claims $claims URL paths claimed during the assignment
     * @param Kind::Category|Kind::Menu $kind Type of the translated rows
     * @param array<int, \stdClass> $rows Translated categories or menu items
     * @param \Closure(\stdClass): RoutePath $route Returns the URL path Joomla uses for the translation
     * @return array<int|string, UrlKey> Record keys and language domains of the translations by ID
     */
    private function claimTranslations( Claims $claims, Kind $kind, array $rows, \Closure $route ): array
    {
        $keys = [];

        foreach( $rows as $row )
        {
            $domain = $this->context->domains->domain( Row::language( $row ) );
            $claim = UrlClaim::translation( $kind, $row->id, $route( $row )->path(), $domain );

            $claims->claim( $claim );
            $keys[$row->id] = $claim->urlKey;
        }

        return $keys;
    }


    /**
     * Returns the translated menu items which get page variants, see CategoryItems::translationPaged().
     *
     * Translated URL and alias menu items can point to other translated menu items, which only have page variants
     * if they pass the check themselves. So translations pointing to dropped ones are dropped too like Urls::menuVariant()
     * returns FALSE for them. Dropping translations never adds ones, so only the translations which asked for a dropped
     * one are checked again until no more translations are dropped.
     *
     * @param array<int, \stdClass> $rows Translations of the menu items of the imported menu with own pages
     * @return array<int, \stdClass> Translations with page variants
     */
    private function pagedTranslations( array $rows ): array
    {
        $categoryItems = $this->context->categoryItems;
        $paged = $dependents = [];

        foreach( $rows as $row ) {
            $paged[(string) $row->id] = true;
        }

        $queue = array_keys( $rows );

        while( $queue !== [] )
        {
            $idx = array_shift( $queue );
            $row = $rows[$idx];

            if( !isset( $paged[(string) $row->id] ) ) {
                continue;
            }

            // remember which translations the check asked for, they decide if it must be repeated
            $variant = function( int|string $id ) use ( &$paged, &$dependents, $idx ) {
                $dependents[(string) $id][$idx] = $idx;
                return isset( $paged[(string) $id] );
            };

            if( !$categoryItems->translationPaged( $row, $variant ) )
            {
                unset( $paged[(string) $row->id] );
                $queue = [...$queue, ...array_values( $dependents[(string) $row->id] ?? [] )];
            }
        }

        return array_values( array_filter( $rows, fn( \stdClass $row ) => isset( $paged[(string) $row->id] ) ) );
    }


    /**
     * Returns the Joomla redirects of page URLs whose targets are supported, see \Aimeos\Cms\Concerns\Joomla\Redirects::importRedirects().
     *
     * Redirects whose targets aren't supported, e.g. Joomla URLs of articles which aren't imported or "javascript:" URLs,
     * would keep their old URL paths from the aliases claimed later although they aren't imported themselves. Their
     * targets are resolved like the import does by the links of the URL paths assigned without redirects. Only Joomla
     * URLs like "index.php?option=com_content&view=article&id=5" are checked against the imported pages, URL paths and
     * URLs of other sites are kept unchecked because they can point to pages which aren't part of the Joomla site, see
     * Links::redirectTarget(). Whether a target resolves only depends on which rows claim URL paths, not on the
     * paths themselves, so the redirects claiming their old URL paths don't change it.
     *
     * @param iterable<\stdClass> $redirects Joomla redirects with their old and new URLs
     * @param Claims $claims URL paths claimed before the redirects, they aren't changed
     * @param array<int|string, UrlClaim> $later Claims of the rows claiming their paths after the redirects
     * @param AssignedUrls $keys Keys and home pages of the rows the claimed URL paths are added to
     * @return array{0: list<KeptRedirect>, 1: list<KeptRedirect>} Redirects with the old URL paths of pages whose targets
     *  are supported and the ones whose targets aren't, the ones of other sites or with query strings are skipped
     */
    private function importedRedirects( iterable $redirects, Claims $claims, array $later, AssignedUrls $keys ): array
    {
        $list = [];

        foreach( $redirects as $redirect )
        {
            if( ( $path = $this->origin->redirectPath( $url = (string) $redirect->old_url ) ) !== null ) {
                $list[] = new KeptRedirect( $path, (string) $redirect->new_url, $this->redirectDir( $url, $path ) );
            }
        }

        if( $list === [] ) {
            return [[], []];
        }

        $all = clone $claims;

        foreach( $later as $claim ) {
            $all->claim( $claim );
        }

        $links = ( $this->linker )( $keys->withClaims( $all ) );
        $imported = $unsupported = [];

        foreach( $list as $redirect )
        {
            if( $links->redirectTarget( $redirect ) !== null ) {
                $imported[] = $redirect;
            } else {
                $unsupported[] = $redirect;
            }
        }

        return [$imported, $unsupported];
    }


    /**
     * Keeps the Joomla redirects whose old URL paths aren't used by the imported pages.
     *
     * @param Claims $claims URL paths claimed during the assignment
     * @param list<KeptRedirect> $redirects Joomla redirects with the old URL paths of pages and their new URLs
     * @return list<KeptRedirect> Kept Joomla redirects
     */
    private function keepRedirects( Claims $claims, array $redirects ): array
    {
        return array_values( array_filter( $redirects, fn( KeptRedirect $redirect ) => $claims->claimPath( $redirect->path ) ) );
    }


    /**
     * Returns the directory relative new URLs of the redirect are relative to, like browsers resolve them.
     *
     * Old URLs ending with a slash like "old/page/" are directories themselves, so "new" is "old/page/new" then.
     *
     * @param string $url Old URL of the Joomla redirect as it's stored
     * @param string $path URL path of the old URL without leading and trailing slash
     * @return string Directory without leading and trailing slash, empty for the root directory
     */
    private function redirectDir( string $url, string $path ): string
    {
        [$raw] = Uri::split( trim( $url ) );
        return str_ends_with( $raw, '/' ) ? $path : ltrim( dirname( '/' . $path ), '/' );
    }


    /**
     * Returns the claims of the translated articles in the domains of their languages.
     *
     * @param array<int|string, TranslationRoute> $translations URL paths and language tags of the translated articles by ID
     * @return array<int|string, UrlClaim> Claims of the translated articles by ID
     */
    private function articleTranslations( array $translations ): array
    {
        $claims = [];

        foreach( $translations as $id => $route ) {
            $claims[$id] = UrlClaim::translation( Kind::Article, $id, $route->path, $this->context->domains->domain( $route->tag ) );
        }

        return $claims;
    }


    /**
     * Returns the IDs of the articles or translated articles whose URL paths are the ones of their menu items.
     *
     * Joomla uses the URL path of the menu item linking to the article, see Paths::articleRoute(), which can be
     * one of any menu, not only of the imported one. Like the Joomla router, which matches the paths of the menu items
     * first, these articles claim their paths before the categories, so categories with the same route get their ID
     * appended instead. Home menu items have no URL path, so their articles use the routes of their categories.
     *
     * @param Articles $articles Imported articles with the menu items Joomla uses for them and their translations
     * @param array<int|string, mixed> $rows Routes or claims of the articles or translated articles by ID
     * @return array<int|string, true> IDs of the articles or translated articles as keys
     */
    private function menuRouted( Articles $articles, array $rows ): array
    {
        $ids = [];

        foreach( array_keys( $rows ) as $id )
        {
            if( MenuItem::withoutHome( $articles->items[$id] ?? null ) !== null ) {
                $ids[$id] = true;
            }
        }

        return $ids;
    }


    /**
     * Returns the URLs of the home pages the article imported into the root page and its translations link to.
     *
     * Like the import of the root page variants, the home menu items of the other languages are shown instead of
     * the translations, so only translations which are the article of the home menu item of their language link to it.
     *
     * @param int $id ID of the article imported into the root page
     * @param list<\stdClass> $translations Associated articles in other languages
     * @param array<string, \stdClass> $otherHomes Home menu items of the other languages by language tag
     * @return array<int|string, string> URLs of the home pages by article ID
     */
    private function homes( int $id, array $translations, array $otherHomes ): array
    {
        $homes = [$id => '/'];

        foreach( $translations as $row )
        {
            $shown = $this->homeShows( $row, $otherHomes, fn( \stdClass $other ) => MenuItem::article( $other ) === (int) $row->id, true );

            // translations in known languages link to the home page of their language, the root of the language domain if it has one
            if( $shown && ( $url = $this->context->domains->homeUrl( Row::language( $row ) ) ) !== null ) {
                $homes[$row->id] = $url;
            }
        }

        return $homes;
    }


    /**
     * Returns the URLs of the home pages showing the category of the home menu item and its translations.
     *
     * Like the import of the root page variants, the translated home menu items are shown in their languages, the
     * home menu items of the other languages instead if there are any. So the translated categories link to the
     * home page of their language if its translated home menu item exists or its home menu item shows the translation.
     *
     * @param Folders $folders Translated categories and menu items grouped by category and menu item ID
     * @param \stdClass $item Home menu item of the site showing the category on the root page
     * @param int $catId ID of the category shown on the root page
     * @param array<string, \stdClass> $otherHomes Home menu items of the other languages by language tag
     * @return array<int|string, string> URLs of the home pages by category ID
     */
    private function categoryHomes( Folders $folders, \stdClass $item, int $catId, array $otherHomes ): array
    {
        $urls = [$catId => '/'];
        $translated = [];

        foreach( $folders->menuTranslations[$item->id] ?? [] as $row ) {
            $translated[Row::language( $row )] = true;
        }

        foreach( $folders->categoryTranslations[$catId] ?? [] as $row )
        {
            $tag = Row::language( $row );
            $shows = function( \stdClass $other ) use ( $row ) {
                $link = MenuLink::of( $other );
                return $link->kind === MenuLink::KIND_CATEGORY && $link->id === (int) $row->id;
            };

            if( $this->homeShows( $row, $otherHomes, $shows, isset( $translated[$tag] ) ) && ( $url = $this->context->domains->homeUrl( $tag ) ) !== null ) {
                $urls[$row->id] = $url;
            }
        }

        return $urls;
    }


    /**
     * Tests if the root page variant of the language of the translation shows it, so the translation links to the home page.
     *
     * This is the single rule of the home menu items of the other languages: with language filter, they replace the
     * translations of the home menu item or of its article in their languages, see Homes::otherHomes(). So in languages with
     * own home menu item, the translation is only shown if that menu item shows it, otherwise if the root page variant of
     * the language is the translation or shows it. The callers only differ in what "shows" means: homes() compares the
     * articles, categoryHomes() the categories and the root page variant of a category exists if the home menu item is
     * translated into the language. reserveHomes() applies the same rule to the languages instead of single translations:
     * the languages of the translations without own home menu item and the ones of the home menu items Joomla shows get
     * root page variants.
     *
     * @param \stdClass $row Translated article or category
     * @param array<string, \stdClass> $otherHomes Home menu items of the other languages by language tag
     * @param \Closure(\stdClass): bool $shows Tests if the home menu item of the language shows the translation
     * @param bool $variant TRUE if the root page variant of the language shows the translation if there's no home menu item of the language
     */
    private function homeShows( \stdClass $row, array $otherHomes, \Closure $shows, bool $variant ): bool
    {
        $other = $otherHomes[Row::language( $row )] ?? null;
        return $other !== null ? $shows( $other ) : $variant;
    }
}

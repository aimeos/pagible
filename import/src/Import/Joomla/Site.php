<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;

use Illuminate\Support\Collection;


/**
 * Settings of the Joomla site shared by the classes importing it.
 *
 * The site reads its settings (categories, component defaults, language filter, version and site language) and the
 * collaborators they depend on (access checks, languages, plugins and extensions) from the Joomla database, so the
 * settings are always derived from the same data. Classes get the site and use its methods for the checks combining
 * the settings with the language policy, e.g. category(), onSite() or shows(). The reader is only used to read the
 * settings, the objects importing the site are created by Wiring.
 */
class Site
{
    /** Option of the languages component containing the default language of the site */
    protected const SITE_LANGUAGE = 'site';

    /** Checks if guests can see the articles, categories, menu items and modules */
    public readonly Access $access;
    /** Installed extensions with their options */
    public readonly Extensions $extensions;
    /** Maps the Joomla language tags to the languages of the pages */
    public readonly Languages $languages;
    /** Checks for the enabled plugins guests can see */
    public readonly Plugins $plugins;
    /** @var array<string, mixed> Options of the articles component used if the articles, categories and menu items don't override them */
    public readonly array $defaults;
    /** If Joomla shows the articles and menu items only on the sites of their languages */
    public readonly bool $languageFilter;
    /** Major Joomla version, sites of unknown versions are handled like the current Joomla versions */
    public readonly Version $version;
    /** Joomla language tag of the site the pages for all languages are shown in, NULL if no usable language is imported, see description() */
    protected readonly ?string $siteTag;


    /**
     * Initializes the object and reads the settings of the Joomla site.
     *
     * @param Reader $reader Reader for the Joomla database
     * @param Collection<array-key, \stdClass> $categories Categories of all states by ID
     * @param array<int|string, mixed> $options Joomla language tags imported as translations with their codes and optional URLs like "de-DE:de"
     * @param string $lang Language code of the imported pages
     */
    public function __construct( Reader $reader, public readonly Collection $categories, array $options = [], string $lang = 'en' )
    {
        $levels = $reader->levels();
        $this->extensions = $reader->extensions();
        $this->access = new Access( $levels, $categories );
        // the plugins use the view levels of the access checks
        $this->plugins = new Plugins( $this->extensions, $levels );

        $usable = self::languageCheck( $this->access, $this->extensions->siteLanguages() );
        $this->languageFilter = $this->plugins->languageFilter();
        // with language filter, Joomla only shows the content of the content languages of the languages table
        $this->languages = new Languages( $reader->languages(), $options, $lang, $usable, $this->languageFilter );
        $this->version = $this->extensions->version();
        $this->defaults = self::contentDefaults( $this->extensions );

        $component = $this->extensions->componentParams( Extensions::COMPONENT_LANGUAGES );
        $default = Value::string( $component[self::SITE_LANGUAGE] ?? null ) ?: Languages::DEFAULT_TAG;
        $this->siteTag = self::siteTag( $this->languages, $this->languageFilter, $default );
    }


    /**
     * Reads the settings of the Joomla site including all categories.
     *
     * @param Reader $reader Reader for the Joomla database
     * @param array<int|string, mixed> $options Joomla language tags imported as translations with their codes and optional URLs like "de-DE:de"
     * @param string $lang Language code of the imported pages
     */
    public static function read( Reader $reader, array $options, string $lang ): self
    {
        return new self( $reader, $reader->categories(), $options, $lang );
    }


    /**
     * Returns the category if Joomla shows its articles because neither the category nor its parents are unpublished or trashed.
     *
     * @return \stdClass|null Category or NULL if it's unknown or hidden
     */
    public function category( int $catId ): ?\stdClass
    {
        $category = $this->categories->get( $catId );
        return $category && !$this->access->categoryHidden( $catId ) ? $category : null;
    }


    /**
     * Returns the IDs of the category and its parent categories Joomla knows.
     *
     * Joomla only loads the published categories guests can see, so the parents aren't known if
     * one of the categories is unpublished, archived or restricted. Unlike RouteCategories::lineage(),
     * which only gets the routed categories and needs the path up to the root, the path may end at an unknown category.
     *
     * @return list<int> Category IDs, the category first, only the category if a category of the path isn't public
     */
    public function categoryIds( int $catId ): array
    {
        $lineage = CategoryTree::lineage( $this->categories, $catId );

        foreach( $lineage->categories as $category )
        {
            if( !$this->access->categoryPublic( $category ) ) {
                return [$catId];
            }
        }

        // the ID of the first unknown category like the root is part of the path
        return $lineage->pathIds();
    }


    /**
     * Returns new menu rules selecting the menu items like Joomla does on this site.
     *
     * The menu rules remember the menu items selected before, so each call returns new ones and Routes selects
     * its menu items by a fresh copy, see MenuRules::fresh().
     */
    public function menuRules(): MenuRules
    {
        return new MenuRules( $this->languageFilter );
    }


    /**
     * Tests if Joomla uses only the menu items for all languages for articles and categories for all languages.
     *
     * Joomla 3 does so with language filter, newer versions use the menu items of the imported language too.
     */
    public function allLanguageItemsOnly(): bool
    {
        return $this->languageFilter && $this->version->upTo( Version::JOOMLA_3 );
    }


    /**
     * Returns the meta description of the content language Joomla shows the page in.
     *
     * Joomla uses it for pages without own meta description, the global one of the site configuration isn't available.
     *
     * @param string $tag Joomla language tag of the article or category
     * @return string Meta description or an empty string if there's none
     */
    public function description( string $tag ): string
    {
        // with language filter, the articles and categories of a language are shown on the site of their language
        return $this->languages->description( $this->languageFilter && $tag !== Languages::ALL ? $tag : $this->siteTag );
    }


    /**
     * Tests if Joomla shows the content of the language on the site of the imported language.
     *
     * The language filter shows the content like articles and menu items only on the sites of their languages.
     *
     * @param string $tag Joomla language tag of the content, e.g. Row::language() of a row
     */
    public function onSite( string $tag ): bool
    {
        return !$this->languageFilter || $this->languages->source( $tag );
    }


    /**
     * Tests if Joomla shows the content of the language on the site of another imported language.
     *
     * Only the language filter shows the content on the sites of other languages.
     *
     * @param string $tag Joomla language tag of the content
     */
    public function onOtherSite( string $tag ): bool
    {
        return $this->languageFilter && $this->languages->other( $tag );
    }


    /**
     * Tests if the content of the language is in the language of the page or for all languages.
     *
     * The language of the page is the one of its article or category, pages for all languages and sites without
     * language filter use the imported language.
     *
     * @param string $language Joomla language tag of the content shown on the page, e.g. a keyword tag
     * @param string $tag Joomla language tag of the article or category of the page
     */
    public function inPageLanguage( string $language, string $tag ): bool
    {
        if( $language === Languages::ALL ) {
            return true;
        }

        if( $this->languageFilter && $tag !== Languages::ALL ) {
            return $language === $tag;
        }

        return $this->languages->source( $language );
    }


    /**
     * Tests if Joomla shows a module or field of the language on the page of the article or category.
     *
     * Without language filter, all modules and fields are shown, otherwise only the ones inPageLanguage().
     *
     * Other checks of the languages differ: itemShows() uses the menu items for articles like this except in Joomla 3,
     * Row::inLanguage() matches the menu items of the routes of a language and Homes::home() prefers the
     * home menu item of the import language over the one for all languages.
     *
     * @param string $language Joomla language tag of the module or field
     * @param string $tag Joomla language tag of the article or category
     */
    public function shows( string $language, string $tag ): bool
    {
        return !$this->languageFilter || $this->inPageLanguage( $language, $tag );
    }


    /**
     * Tests if Joomla uses a menu item of the language for an article in the language of the tag.
     *
     * Like shows(), but Joomla 3 uses only the menu items for all languages for articles for all languages
     * with language filter, see allLanguageItemsOnly(). Without language filter, MenuRules::take()
     * only uses the menu items for all languages.
     *
     * @param string $language Joomla language tag of the menu item
     * @param string $tag Joomla language tag of the article
     */
    public function itemShows( string $language, string $tag ): bool
    {
        if( $tag === Languages::ALL && $this->allLanguageItemsOnly() ) {
            return $language === Languages::ALL;
        }

        return $this->shows( $language, $tag );
    }


    /**
     * Returns the function testing if Joomla uses a content language.
     *
     * Joomla only uses the content languages guests can access whose site language is installed and enabled.
     *
     * @param array<int, string>|null $installed Tags of the installed and enabled site languages or NULL if unknown
     * @return \Closure(\stdClass): bool Function returning TRUE if the content language is usable
     */
    protected static function languageCheck( Access $access, ?array $installed ): \Closure
    {
        $enabled = fn( \stdClass $row ) => $installed === null || in_array( (string) ( $row->lang_code ?? '' ), $installed, true );

        return fn( \stdClass $row ) => $access->languagePublic( $row ) && $enabled( $row );
    }


    /**
     * Returns the options of the articles component used as defaults of the articles, categories, menu items and the router.
     *
     * Menu items use the page heading option of the menus component if neither they nor the articles component set it.
     *
     * @return array<string, mixed> Filled options by name
     */
    protected static function contentDefaults( Extensions $extensions ): array
    {
        $params = Value::filled( $extensions->componentParams( Extensions::COMPONENT_CONTENT ) );
        $heading = $extensions->componentParams( Extensions::COMPONENT_MENUS )[Options::PAGE_HEADING_SHOWN] ?? null;

        if( !isset( $params[Options::PAGE_HEADING_SHOWN] ) && $heading !== null && $heading !== '' ) {
            $params[Options::PAGE_HEADING_SHOWN] = $heading;
        }

        return $params;
    }


    /**
     * Returns the Joomla language tag of the pages Joomla shows in the imported language.
     *
     * Without language filter, Joomla shows all pages in the default site language, otherwise the ones for all languages in the import language.
     *
     * @param Languages $languages Usable content languages and the imported ones
     * @param bool $filter TRUE if the language filter is enabled
     * @param string $default Joomla language tag of the default site language
     * @return string|null Joomla language tag or NULL if no usable language is imported
     */
    protected static function siteTag( Languages $languages, bool $filter, string $default ): ?string
    {
        if( !$filter ) {
            return $default;
        }

        foreach( $languages->tags() as $tag )
        {
            if( $tag !== Languages::ALL && $languages->source( $tag ) ) {
                return $tag;
            }
        }

        return null;
    }
}

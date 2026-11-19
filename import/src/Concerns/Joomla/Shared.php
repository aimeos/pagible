<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Joomla;

use Aimeos\Cms\DB as CmsDB;
use Aimeos\Cms\Import\Joomla;
use Aimeos\Cms\Import\Pages;
use Aimeos\Cms\Import\Status;
use Aimeos\Cms\Models\PageView;


/**
 * Objects of the current run and methods of the Joomla import shared by the traits importing the articles, the home pages and the menu.
 *
 * Used once by JoomlaImport, so the objects of the run are declared in one place only. The traits Articles,
 * Categories, Changes, Home, Menu, Redirects, Reports and Vanished rely on the class using this trait too,
 * the methods of JoomlaImport they call are declared here.
 */
trait Shared
{
    /** Objects importing the Joomla site, set by handle() before anything is imported */
    private Joomla\Wiring $wiring;
    /** Imported articles and folders and the objects building the pages, set by startRun() before anything is imported */
    private Joomla\Run $run;


    /**
     * Creates the objects of the run and the modification times of the articles, see JoomlaImport::startRun().
     *
     * @param Joomla\Reader $reader Reader for the Joomla database
     * @param Joomla\Articles $articles Imported articles and translations
     * @param PageView|null $root Root page or NULL if the dry run finds none, an import would create it
     */
    abstract protected function startRun( Joomla\Reader $reader, Joomla\Articles $articles, ?PageView $root ): void;


    /**
     * Tests if the import may update the root page, see JoomlaImport::updatesRoot().
     *
     * @param PageView|null $root Root page or NULL if the dry run finds none, an import would create it
     */
    abstract protected function updatesRoot( ?PageView $root ): bool;


    /**
     * Returns the object splitting the articles and menu items into chunks, see JoomlaImport::chunks().
     *
     * @param Joomla\Articles $articles Imported articles and translations
     */
    abstract protected function chunks( Joomla\Articles $articles ): Joomla\Chunks;


    /**
     * Returns the chunks of the articles in import order, see JoomlaImport::articleChunks().
     *
     * @param Joomla\Chunks $chunks Splits the articles into chunks, see chunks()
     * @param Joomla\Articles $articles Imported articles and translations
     * @return \Generator<int, \Illuminate\Support\Collection<int|string, int>> Positions of the categories by article ID per chunk
     */
    abstract protected function articleChunks( Joomla\Chunks $chunks, Joomla\Articles $articles ): \Generator;


    /**
     * Fetches the articles imported next and their texts and pages at once, see Changes::prefetch().
     *
     * @param array<int|string> $ids IDs of the articles imported next
     * @param PageView|null $root Root page if the article of the home menu item is imported next
     */
    abstract protected function prefetch( array $ids, ?PageView $root = null ): void;


    /**
     * Returns the prefetched article if it's imported and has no page yet, see Changes::pendingArticle().
     *
     * @throws \LogicException If the pending article wasn't prefetched for the current chunk
     */
    abstract protected function pendingArticle( int|string $id ): ?\stdClass;


    /**
     * Imports the article within a transaction and returns its page or NULL on failure, see Articles::tryImportArticle().
     */
    abstract protected function tryImportArticle( \stdClass $article, PageView $parent ): ?PageView;


    /**
     * Fetches the pages of the folders and link pages saved next, the variants of the translated ones and the files of the images at once.
     *
     * Unlike the pages and variants of the articles, which are replaced per chunk, the ones of the folders are added,
     * so pageByPath(), variants() and createFile() don't fetch them one by one. Known URL paths aren't fetched again.
     *
     * @param array<string, bool> $slugs TRUE if the folder has translations whose variants are needed by URL path
     * @param array<int, string> $images Paths and URLs of the images of the folders
     */
    protected function prefetchFolders( array $slugs, array $images = [] ): void
    {
        $missing = array_filter( array_keys( $slugs ), fn( $slug ) => !array_key_exists( trim( (string) $slug, '/' ), $this->prefetchedPages ) );
        $this->prefetchPages( array_map( 'strval', $missing ) );

        $pageIds = [];

        foreach( array_keys( array_filter( $slugs ) ) as $slug )
        {
            if( $page = $this->prefetchedPages[trim( (string) $slug, '/' )] ?? null ) {
                $pageIds[] = (string) $page->id;
            }
        }

        // the variants of the articles of the current chunk are kept and must not be evicted, remaining folders fetch their own
        $pageIds = array_diff( array_unique( $pageIds ), array_map( 'strval', array_keys( $this->prefetchedVariants ) ) );
        $this->prefetchVariants( array_slice( $pageIds, 0, max( 0, CmsDB::IN_SIZE - count( $this->prefetchedVariants ) ) ), true );
        // the files of the articles of the current chunk are still used
        $this->prefetchFiles( array_map( $this->resolveFile( ... ), $images ), false );
    }


    /**
     * Shows why the saved folder or link page of a category or menu item is disabled and saves its translations as variants.
     *
     * @param array{page: PageView, status: Status} $saved Saved page and its status
     * @param \stdClass $row Category or menu item of the page
     * @param Joomla\Kind::Category|Joomla\Kind::Menu $kind Type of the row
     * @param string $slug URL path of the page
     * @param string|null $reason Why the page is disabled or NULL if it's published
     * @return PageView Saved page
     */
    protected function savePageTranslations( array $saved, \stdClass $row, Joomla\Kind $kind, string $slug, ?string $reason ): PageView
    {
        $page = $saved['page'];
        $translations = $kind === Joomla\Kind::Category ? $this->run->categoryTranslations( $row ) : $this->run->menuTranslations( $row );
        $label = Joomla\Row::title( $row ) . " (/{$slug})";

        $this->notice( 'Disabled', $label, $reason );
        $variants = $this->savedVariants( $page, $saved['status'], $translations );
        $this->saveTranslations( $page, $translations, $kind, $variants, $reason !== null );
        $this->formerTranslations( $page, $translations, $variants, $label );

        return $page;
    }


    /**
     * Returns the variants of a saved page needed by saveTranslations().
     *
     * @param Status $status Status of the saved page
     * @param list<\stdClass> $rows Associated articles, categories or menu items in other languages
     * @return array<string, PageView> Variants by language code, none for new pages and pages without translations
     */
    protected function savedVariants( PageView $page, Status $status, array $rows ): array
    {
        // the variants of all languages including trashed ones are fetched at once
        return $status !== Status::Imported && $rows !== [] ? $this->variants( $page ) : [];
    }


    /**
     * Creates or updates the variants of the page from the associated articles, categories or menu items.
     *
     * @param list<\stdClass> $rows Associated articles, categories or menu items in other languages
     * @param Joomla\Kind $kind Type of the rows
     * @param array<string, PageView> $variants Variants of the page fetched before by language code
     * @param bool $disabled TRUE if the page is disabled, so its translations are disabled too, FALSE to use their own status
     */
    protected function saveTranslations( PageView $page, array $rows, Joomla\Kind $kind, array $variants, bool $disabled = false ): void
    {
        foreach( $rows as $row ) {
            $this->saveTranslation( $page, Joomla\Translation::of( $row, $kind ), $variants, $disabled );
        }
    }


    /**
     * Creates or updates the variant of the page from an associated article, category or menu item.
     *
     * Variants of the root page use the language code as URL path or the root of their separate domain.
     *
     * @param Joomla\Translation $translation Associated row and the language it's shown in
     * @param array<string, PageView> $variants Variants of the page fetched before by language code
     * @param bool $disabled TRUE if the page is disabled, so the translation is disabled too, FALSE to use its own status
     */
    protected function saveTranslation( PageView $page, Joomla\Translation $translation, array $variants, bool $disabled = false ): void
    {
        $row = $translation->row;
        $status = $disabled ? Pages::STATUS_DISABLED : null;

        if( !( $language = $this->wiring->site->languages->language( $translation->tag ) ) ) {
            return;
        }

        // translations changed in Pagible aren't overwritten
        $existing = $variants[$language['lang']] ?? null;

        if( $this->skipsTranslation( $existing, Joomla\Row::title( $row ), $language['lang'] ) ) {
            return;
        }

        $slug = $page->tag === Pages::TAG_ROOT ? $this->run->urls->rootPath( $language ) : $this->run->urls->translatedPath( $translation );
        // translations without own menu item are shown in the menu item of their category in their language
        $variant = $this->run->pages->translation( $translation, $slug, $this->wiring->router->languageRoutes( $translation->tag ), $status );

        if( $variant )
        {
            $this->notice( $variant->translationNotice(), Joomla\Row::title( $row ) . " (/{$slug}) [{$language['lang']}]", $variant->reason );
            $this->saveVariant( $page, $language, $slug, $variant->data, $variant->content, $existing );
        }
    }


    /**
     * Warns about the variants of the import whose Joomla translation isn't imported any more.
     *
     * Removed Joomla associations or translations which aren't imported any more don't delete the variants of the pages,
     * they are kept with their last imported content until they are deleted in Pagible. Only the variants fetched for
     * the translations are checked, so pages which lost all their translations aren't reported. Trashed variants and
     * variants changed in Pagible are ignored.
     *
     * @param list<\stdClass> $rows All associated articles, categories or menu items in other languages
     * @param array<string, PageView> $variants Variants of the page fetched before by language code
     * @param string $subject Title and URL path of the page
     */
    protected function formerTranslations( PageView $page, array $rows, array $variants, string $subject ): void
    {
        $languages = $this->wiring->site->languages;
        $langs = [(string) $page->lang => true, (string) $page->source => true];

        foreach( $rows as $row )
        {
            if( $language = $languages->language( Joomla\Row::language( $row ) ) ) {
                $langs[$language['lang']] = true;
            }
        }

        foreach( $variants as $lang => $variant )
        {
            if( !isset( $langs[$lang] ) && $variant->editor === $this->editor && $variant->getAttribute( 'variant_deleted_at' ) === null ) {
                $this->warn( "  Former translation: {$subject} [{$lang}] (no Joomla translation in this language is imported any more, e.g. its association was removed, delete the variant in Pagible if it's not needed)" );
            }
        }
    }


    /**
     * Shows what was done with a page, menu item or redirect and why, like the messages of the base importer.
     *
     * The message has the format "  {what}: {subject} ({reason})", e.g. "  Disabled: About (/about) (it expired on ...)".
     *
     * @param string $what What was done, e.g. "Disabled" or "Skipped menu item"
     * @param string $subject Title, label or URL path of the page, menu item or redirect
     * @param string|null $reason Why it was done or NULL if nothing is shown
     */
    protected function notice( string $what, string $subject, ?string $reason ): void
    {
        if( $reason !== null ) {
            $this->warn( "  {$what}: {$subject} ({$reason})" );
        }
    }
}

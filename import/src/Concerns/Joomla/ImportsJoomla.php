<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Joomla;

use Aimeos\Cms\Import\JoomlaKind;
use Aimeos\Cms\Import\JoomlaRow;
use Aimeos\Cms\Import\JoomlaRun;
use Aimeos\Cms\Import\JoomlaTranslation;
use Aimeos\Cms\Import\JoomlaWiring;
use Aimeos\Cms\Import\Pages;
use Aimeos\Cms\Import\Status;
use Aimeos\Cms\Models\PageView;


/**
 * Objects of the current run and methods of the Joomla import shared by the traits importing the articles, the home pages and the menu.
 *
 * Used once by JoomlaImport, so the objects of the run are declared in one place only. The traits ImportsJoomlaArticles,
 * ImportsJoomlaHome and ImportsJoomlaMenu rely on the class using this trait too.
 */
trait ImportsJoomla
{
    /** Objects importing the Joomla site, set by handle() together with the run before anything is imported */
    private JoomlaWiring $wiring;
    /** Imported articles and folders and the objects building the pages, set by handle() before anything is imported */
    private JoomlaRun $run;


    /**
     * Fetches the articles imported next and their texts and pages at once, see JoomlaImport::prefetch().
     *
     * @param array<int|string> $ids IDs of the articles imported next
     * @param PageView|null $root Root page if the article of the home menu item is imported next
     */
    abstract protected function prefetch( array $ids, ?PageView $root = null ): void;


    /**
     * Returns the prefetched article if it's imported and has no page yet, see JoomlaImport::pendingArticle().
     *
     * @throws \LogicException If the pending article wasn't prefetched for the current chunk
     */
    abstract protected function pendingArticle( int|string $id ): ?\stdClass;


    /**
     * Imports the article within a transaction and returns its page or NULL on failure, see JoomlaImport::tryImportArticle().
     */
    abstract protected function tryImportArticle( \stdClass $article, PageView $parent ): ?PageView;


    /**
     * Shows why the saved folder or link page of a category or menu item is disabled and saves its variants.
     *
     * @param array{page: PageView, status: Status} $saved Saved page and its status
     * @param \stdClass $row Category or menu item of the page
     * @param JoomlaKind::CATEGORY|JoomlaKind::MENU $kind Type of the row
     * @param string $slug URL path of the page
     * @param string|null $reason Why the page is disabled or NULL if it's published
     * @return PageView Saved page
     */
    protected function savedPage( array $saved, \stdClass $row, string $kind, string $slug, ?string $reason ): PageView
    {
        $page = $saved['page'];
        $translations = $kind === JoomlaKind::CATEGORY ? $this->run->categoryTranslations( $row ) : $this->run->menuTranslations( $row );

        $this->notice( 'Disabled', JoomlaRow::title( $row ) . " (/{$slug})", $reason );
        $variants = $this->savedVariants( $page, $saved['status'], $translations );
        $this->saveTranslations( $page, $translations, $kind, $variants, $reason !== null );
        $this->formerTranslations( $page, $translations, $variants, JoomlaRow::title( $row ) . " (/{$slug})" );

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
     * @param JoomlaKind::ARTICLE|JoomlaKind::CATEGORY|JoomlaKind::MENU $kind Type of the rows
     * @param array<string, PageView> $variants Variants of the page fetched before by language code
     * @param bool $disabled TRUE if the page is disabled, so its translations are disabled too, FALSE to use their own status
     */
    protected function saveTranslations( PageView $page, array $rows, string $kind, array $variants, bool $disabled = false ): void
    {
        foreach( $rows as $row ) {
            $this->saveTranslation( $page, JoomlaTranslation::of( $row, $kind ), $variants, $disabled );
        }
    }


    /**
     * Creates or updates the variant of the page from an associated article, category or menu item.
     *
     * Variants of the root page use the language code as URL path or the root of their separate domain.
     *
     * @param JoomlaTranslation $translation Associated row and the language it's shown in
     * @param array<string, PageView> $variants Variants of the page fetched before by language code
     * @param bool $disabled TRUE if the page is disabled, so the translation is disabled too, FALSE to use its own status
     */
    protected function saveTranslation( PageView $page, JoomlaTranslation $translation, array $variants, bool $disabled = false ): void
    {
        $row = $translation->row;
        $status = $disabled ? Pages::STATUS_DISABLED : null;

        if( !( $language = $this->wiring->site->languages->language( $translation->tag ) ) ) {
            return;
        }

        // translations changed in Pagible aren't overwritten
        $existing = $variants[$language['lang']] ?? null;

        if( $this->skipsTranslation( $existing, JoomlaRow::title( $row ), $language['lang'] ) ) {
            return;
        }

        $urls = $this->run->urls;
        $slug = $page->tag === Pages::TAG_ROOT ? $urls->rootPath( $language ) : match( $translation->kind ) {
            JoomlaKind::ARTICLE => $urls->articleTranslationPath( $row ),
            JoomlaKind::CATEGORY => $urls->categoryTranslationPath( $row ),
            JoomlaKind::MENU => $urls->menuTranslationPath( $row ),
        };
        // translations without own menu item are shown in the menu item of their category in their language
        $routes = $this->wiring->router->languageRoutes( $translation->tag );
        $pages = $this->run->pages;

        // translations are restricted like the pages of the articles, categories and menu items in the import language
        $variant = match( $translation->kind ) {
            JoomlaKind::ARTICLE => $translation->showing
                ? $pages->homeTranslation( $row, $slug, $routes, $translation->showing, $translation->tag )
                : $pages->articlePage( $row, $slug, $routes, $status ),
            // the translation uses the menu item linking to the category, the home menu item of its language is used for the root page instead
            JoomlaKind::CATEGORY => $pages->categoryPage( $row, $slug, $routes, $routes->ownCategoryItem( (int) $row->id ), $status ),
            JoomlaKind::MENU => $pages->menuTranslation( $row, $slug, $status ),
        };

        if( $variant )
        {
            $this->notice( $variant->translationNotice(), JoomlaRow::title( $row ) . " (/{$slug}) [{$language['lang']}]", $variant->reason );
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
            if( $language = $languages->language( JoomlaRow::language( $row ) ) ) {
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

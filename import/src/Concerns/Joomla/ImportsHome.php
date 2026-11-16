<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Joomla;

use Aimeos\Cms\Import\Joomla;
use Aimeos\Cms\Import\Status;
use Aimeos\Cms\Models\PageView;


/**
 * Imports the Joomla home menu item into the root page and the home menu items of the other languages into its variants.
 *
 * Requires the Imports trait used by the same class for the objects of the run and the shared methods.
 */
trait ImportsHome
{
    /**
     * Imports the article of the Joomla home menu item into the root page or uses the root page for the articles of its category.
     *
     * Root pages which weren't created by the import or were changed since aren't overwritten,
     * the article is imported as normal page then.
     *
     * @return bool TRUE if the home menu item uses the root page, FALSE if it's imported like the other menu items
     */
    protected function importHome( \stdClass $menuItem, PageView $root ): bool
    {
        $link = Joomla\MenuLink::of( $menuItem );

        // home menu items listing the subcategories show no category themselves
        if( $link->kind === Joomla\MenuLink::KIND_CATEGORY && $link->id ) {
            return $this->importCategoryHome( $menuItem, $link->id, $root );
        }

        // other home pages like the featured articles keep the root page, the child items are imported below
        if( !( $id = $link->article() ) )
        {
            $this->updateHomeRoot( $menuItem, $root );
            return true;
        }

        return $this->importArticleHome( $menuItem, $id, $root );
    }


    /**
     * Uses the root page for the articles of the category of the Joomla home menu item.
     *
     * Home items of restricted or archived categories are imported like the other ones, so their pages are disabled.
     *
     * @param int $catId ID of the category
     * @return bool TRUE if the root page is used for the category, FALSE if the menu item is imported like the other ones
     */
    protected function importCategoryHome( \stdClass $menuItem, int $catId, PageView $root ): bool
    {
        // the home category is only known if Joomla shows it to guests, so the category exists then
        if( $this->wiring->categoryItems->homeCatId() !== $catId ) {
            return false;
        }

        $category = $this->wiring->site->category( $catId );

        // the articles of the category are imported below the root page
        $this->run->addCategoryPage( $catId, $this->slim( $root ) );
        $this->updateHomeRoot( $menuItem, $root, $category );

        return true;
    }


    /**
     * Imports the article of the Joomla home menu item into the root page.
     *
     * The root page is used for the home menu item also if the article fails to import, so its child items stay below the root page.
     *
     * @param int $id ID of the article
     * @return bool TRUE if the root page is used for the article, FALSE if the article is imported as normal page
     */
    protected function importArticleHome( \stdClass $menuItem, int $id, PageView $root ): bool
    {
        $run = $this->run;

        if( !( $article = $this->pendingArticle( $id ) ) ) {
            return false;
        }

        if( !$run->home->isArticle( $id ) )
        {
            // the reason is NULL if the article doesn't exist, the message isn't shown then
            $this->notice( 'Imported home page as normal page', Joomla\Row::label( $article ), $run->home->reason );
            return false;
        }

        // the result is ignored because the URLs were assigned for the article in the root page, so it isn't imported
        // as normal page if its content fails, the failure is reported by transaction() and the root page is used anyway
        $run->markHandled( $id );
        $this->rootTransaction( Joomla\Row::label( $article ), $root, function() use ( $article, $menuItem, $root, $run ) {
            // the root page is rendered with --since too because the pages don't store which article or menu item it showed
            // before, e.g. the home menu item can be moved to another unchanged article. Like its variants, which are saved
            // anyway because the home menu items of other languages can change too, an unchanged root page keeps its version
            $rendered = $run->pages->homeArticlePage( $article, $menuItem );
            $status = $this->updateRoot( $root, $rendered, $run->translations( $article ), Joomla\Kind::ARTICLE );

            return $this->imported( $article->id, $root, Joomla\Row::title( $article ), '', $this->newRoot ? Status::Imported : $status );
        } );

        return true;
    }


    /**
     * Applies the browser title, the meta data and the category of the home menu item to the root page.
     *
     * Category blogs and lists show the category above its articles. Root pages which weren't created by
     * the import or were changed since aren't overwritten.
     *
     * @param \stdClass|null $category Category of the home menu item or NULL if it shows no category
     */
    protected function updateHomeRoot( \stdClass $menuItem, PageView $root, ?\stdClass $category = null ): void
    {
        if( !$this->isImported( $root ) ) {
            return;
        }

        $run = $this->run;
        $data = $this->pageData( $root->name, $root->path, $root->status, $root->tag );
        $rendered = $run->pages->homePage( $menuItem, $data, $category );

        $this->rootTransaction( Joomla\Row::label( $menuItem ), $root,
            fn() => $this->updateRoot( $root, $rendered, $run->menuTranslations( $menuItem ), Joomla\Kind::MENU ) );
    }


    /**
     * Updates the root page within a transaction and restores its values if the transaction fails.
     *
     * The root page is used by the following records, so it must not keep the values of the rolled back versions.
     *
     * @template T
     * @param string $label Label of the imported record in the error message
     * @param \Closure(): T $fn Import function updating the root page
     * @return T|null Result of the import function or NULL on failure
     */
    protected function rootTransaction( string $label, PageView $root, \Closure $fn ): mixed
    {
        $original = $root->getRawOriginal();
        $attributes = $root->getAttributes();

        if( ( $result = $this->transaction( $label, $fn ) ) === null ) {
            $root->setRawAttributes( $original, true )->setRawAttributes( $attributes );
        }

        return $result;
    }


    /**
     * Updates the root page and its variants.
     *
     * @param Joomla\Page $rendered Page data and content of the root page
     * @param list<\stdClass> $rows Associated articles or menu items in other languages
     * @param Joomla\Kind::ARTICLE|Joomla\Kind::MENU $kind Type of the rows
     * @return Status Status of the root page
     */
    protected function updateRoot( PageView $root, Joomla\Page $rendered, array $rows, string $kind ): Status
    {
        $status = $this->updateVersion( $root, $rendered->data, $rendered->content );
        $this->saveHomeTranslations( $root, $rows, $kind );

        return $status;
    }


    /**
     * Creates or updates the variants of the root page.
     *
     * With language filter, Joomla shows the home menu items of the other languages on the home pages of their sites
     * instead of the associated articles or menu items. Variants of languages without imported translation or home
     * menu item are reported like the former translations of the other pages, see formerTranslations().
     *
     * @param list<\stdClass> $rows Associated articles or menu items in other languages
     * @param Joomla\Kind::ARTICLE|Joomla\Kind::MENU $kind Type of the rows
     */
    protected function saveHomeTranslations( PageView $root, array $rows, string $kind ): void
    {
        $homes = $this->wiring->homes->otherHomes();
        $rows = array_values( array_filter( $rows, fn( $row ) => !isset( $homes[Joomla\Row::language( $row )] ) ) );
        // each language is saved once, so the variants of all languages are fetched once too
        $variants = $rows !== [] || $homes !== [] ? $this->variants( $root ) : [];
        $this->saveTranslations( $root, $rows, $kind, $variants );
        $articles = $this->run->visibleArticles( array_filter( array_map( Joomla\MenuItem::article( ... ), $homes ) ) );
        // the home menu items are in the languages of their tags, so they count as translations of the root page
        $saved = $rows;

        foreach( $homes as $tag => $item )
        {
            if( !( $id = Joomla\MenuItem::article( $item ) ) ) {
                $this->saveTranslation( $root, Joomla\Translation::of( $item, Joomla\Kind::MENU ), $variants );
                $saved[] = $item;
            } elseif( isset( $articles[$id] ) ) {
                // articles for all languages are shown in the language of the home menu item, the same article can be shown by several ones
                $this->saveTranslation( $root, Joomla\Translation::home( $articles[$id], $item, $tag ), $variants );
                $saved[] = $item;
            } else {
                $this->notice( 'Skipped home page', Joomla\Row::label( $item ) . " [{$tag}]", "its article [{$id}] isn't shown by Joomla" );
            }
        }

        $this->formerTranslations( $root, $saved, $variants, "{$root->name} (/)" );
    }
}

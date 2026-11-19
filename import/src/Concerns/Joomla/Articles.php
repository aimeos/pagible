<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Joomla;

use Aimeos\Cms\Import\Joomla;
use Aimeos\Cms\Import\Status;
use Aimeos\Cms\Models\PageView;


/**
 * Imports single Joomla articles below the pages of their categories, JoomlaImport::importContent() imports them in chunks.
 *
 * Requires the Shared and Changes traits used by the same class for the objects of the run and the shared methods.
 */
trait Articles
{
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
        $label = "{$title} (/{$slug})";

        // articles whose translations and menu item names also weren't changed since the given date aren't rendered again,
        // which was decided once by prefetch() because their texts weren't fetched then
        if( $run->kept( $article->id ) && ( $page = $this->previousPage( $slug ) ) )
        {
            $this->moveBelow( $page, $parent );
            // the translations and variants were fetched for the whole chunk by prefetchKept(), pages without translations have no variants
            $this->formerTranslations( $page, $run->translations( $article ), $this->prefetchedVariants( $page ) ?? [], $label );
            return $this->imported( $article->id, $page, $title, $slug, Status::Unchanged );
        }

        $rendered = $run->pages->articlePage( $article, $slug, $this->wiring->router->contentRoutes( Joomla\Row::language( $article ) ) );
        $created = Joomla\Date::first( $article->created ?? null );
        ['page' => $page, 'status' => $status] = $this->savePage( $slug, $rendered->data, $rendered->content, $parent, $created );
        $this->notice( $rendered->notice(), $label, $rendered->reason );

        // teasers are published, so their translations are published too
        $translations = $run->translations( $article );
        $variants = $this->savedVariants( $page, $status, $translations );
        $this->saveTranslations( $page, $translations, Joomla\Kind::Article, $variants, !$rendered->teaser && $rendered->reason !== null );
        $this->formerTranslations( $page, $translations, $variants, $label );

        return $this->imported( $article->id, $page, $title, $slug, $status );
    }


    /**
     * Imports an article within a transaction and logs the result.
     *
     * Articles whose import failed are handled, so other menu items and the category loop don't try and report them again.
     *
     * @return PageView|null Created, updated or unchanged page or NULL on failure
     */
    protected function tryImportArticle( \stdClass $article, PageView $parent ): ?PageView
    {
        $page = $this->transaction( Joomla\Row::label( $article ), fn() => $this->importArticle( $article, $parent ) );

        if( $page === null ) {
            $this->run->markHandled( $article->id );
        }

        return $page;
    }
}

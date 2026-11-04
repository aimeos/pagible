<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\DB;
use Aimeos\Cms\Import\DrupalTerms;
use Aimeos\Cms\Import\Status;
use Aimeos\Cms\Import\Tally;
use Aimeos\Cms\Models\PageView;
use Illuminate\Support\LazyCollection;


/**
 * Imports the taxonomy terms of the Drupal site as hidden pages.
 */
trait ImportsDrupalTerms
{
    /** Counts of the imported term pages */
    protected Tally $termTally;


    /**
     * Imports the taxonomy terms of the vocabularies from the --terms option as hidden pages.
     *
     * Only terms with URL alias are imported. Their pages contain the term description and links to
     * the imported pages of the newest 100 nodes tagged with the term, newest first. Translations of the terms
     * in the imported languages become translations of their pages.
     */
    protected function importTerms( PageView $root ): void
    {
        $vocabularies = array_values( array_filter( array_map( 'trim', explode( ',', $this->stringOption( 'terms' ) ) ) ) );
        $terms = new DrupalTerms( $this->reader, $this->data, $this->languages, $this->urls, $this->content, $this->nodes, $this->translated, fn( string $msg ) => $this->warn( $msg ) );

        foreach( LazyCollection::make( fn() => $terms->all( $vocabularies ) )->chunk( DB::IN_SIZE ) as $items )
        {
            // the pages of the terms and the variants of the pages of translated terms are fetched at once
            $this->prefetchPages( $items->pluck( 'slug' )->all() );
            $this->prefetchVariants( $items->filter( fn( $item ) => !empty( $item['translations'] ) )
                ->map( fn( $item ) => ( $this->prefetchedPages[trim( $item['slug'], '/' )] ?? null )?->id )->filter()->map( fn( $id ) => strval( $id ) )->values()->all() );

            foreach( $items as $item )
            {
                // term pages only link to the pages of the imported nodes
                $nids = array_values( array_filter( $item['nids'], fn( $nid ) => $this->createdPages->has( $nid ) ) );

                $this->termTally->add( $this->transaction( "term [{$item['term']->tid}] {$item['term']->name}",
                    fn() => $this->importTerm( $terms, $item['term'], $item['slug'], $nids, $root, $item['translations'] ) ) );
            }
        }

        $this->subtotals( $this->termTally, 'term pages' );
    }


    /**
     * Imports the taxonomy term as hidden page below the root page.
     *
     * @param DrupalTerms $terms Renders the content of the term pages
     * @param \stdClass $term Record of the taxonomy_term_field_data table
     * @param string $slug URL path of the page
     * @param array<int, int> $nids IDs of the imported nodes tagged with the term
     * @param array<int, array{term: \stdClass, slug: string}> $translations Translations of the term and their URL paths
     * @return Status Status of the term page
     */
    protected function importTerm( DrupalTerms $terms, \stdClass $term, string $slug, array $nids, PageView $root, array $translations = [] ): Status
    {
        $content = $terms->content( $term, $nids );
        $data = $this->pageData( (string) $term->name, $slug, 2 );

        ['page' => $page, 'status' => $status] = $this->savePage( $slug, $data, $content, $root );

        $this->markSaved( $page->variant_id );
        $this->info( '  ' . $status->label() . ': ' . $data['name'] . " (/{$slug})" );

        // the variants of all languages including trashed ones are fetched at once
        $variants = $translations ? $this->variants( $page ) : [];

        foreach( $translations as $translation )
        {
            $language = $this->languages->language( (string) $translation['term']->langcode );

            if( !$language || $language['lang'] === $this->lang ) {
                continue;
            }

            // translations changed in Pagible aren't overwritten
            $existing = $variants[$language['lang']] ?? null;

            if( $existing && $existing->editor !== $this->editor )
            {
                $this->warn( "  Skipped translation: {$translation['term']->name} [{$language['lang']}] (changed in Pagible since the last import)" );
                continue;
            }

            $variant = $this->saveVariant( $page, $language, $translation['slug'], $this->pageData( (string) $translation['term']->name, $translation['slug'], 2 ),
                $terms->content( $translation['term'], $nids ), $existing );

            if( $variant )
            {
                // later translations in the same language use the saved variant
                $variants[$language['lang']] = $variant;
                $this->markSaved( $variant->variant_id );
            }
        }

        return $status;
    }
}

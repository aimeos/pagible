<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Typo3;

use Aimeos\Cms\Import\Pages;
use Aimeos\Cms\Import\Typo3;
use Aimeos\Cms\Models\PageView;
use Illuminate\Support\Collection;


/**
 * Imports the TYPO3 page translations as page variants.
 */
trait Translations
{
    /** @var array<int, int> UIDs of the TYPO3 languages whose translations are imported */
    protected array $langUids = [];

    /** @var array<int, array<int, string>> URL paths of the TYPO3 page translations in the known languages by page UID and language UID */
    protected array $translated = [];

    /** @var array<int, Collection<int, mixed>> TYPO3 page translations of the upcoming pages by page UID */
    protected array $translations = [];


    /**
     * Collects the URL paths of the page translations in the known languages.
     *
     * Only the URL paths of the translations are kept for the links to them, the other
     * columns are fetched for the upcoming pages when they are imported.
     *
     * @return int Number of translations in the known languages
     */
    protected function loadTranslations(): int
    {
        ['slugs' => $this->translated, 'count' => $count, 'unknown' => $unknown] = $this->languages->translations( $this->reader->translatedSlugs() );
        $this->translations = [];

        if( $unknown !== [] ) {
            $this->warn( 'Skipping translations of unknown TYPO3 languages [' . implode( ', ', $unknown ) . '], add --language=<uid>:<code> to import them.' );
        }

        return $count;
    }


    /**
     * Creates or updates the variants of the page from its TYPO3 translations.
     *
     * @param array<string, mixed> $pageData Data of the source variant
     * @param bool $new TRUE if the page has just been created and has no variants yet
     */
    protected function saveTranslations( object $t3Page, PageView $page, array $pageData, bool $new = false ): void
    {
        $sourceDomain = (string) ( $pageData['domain'] ?? '' );
        $records = $this->translationRecords( $t3Page );
        // the variants of all languages including trashed ones are fetched at once
        $variants = $records->isNotEmpty() && !$new ? $this->variants( $page ) : [];
        $translations = [];

        foreach( $records->unique( 'sys_language_uid' ) as $record )
        {
            $langUid = (int) ( $record->sys_language_uid ?? 0 );
            $language = $this->languages->get( $langUid, $this->siteRoot( $t3Page ) );

            if( !$language || $language['lang'] === ( $pageData['lang'] ?? $this->lang ) ) {
                continue;
            }

            if( $this->skipsTranslation( $variants[$language['lang']] ?? null, (string) ( $record->title ?? '' ), $language['lang'] ) ) {
                continue;
            }

            $translations[] = [$record, $langUid, $language, $this->records->page( $t3Page, $langUid )];
        }

        // the file references and files of all languages are fetched at once before the converters are copied
        if( $this->typo3 && $translations )
        {
            $all = Collection::make( array_column( $translations, 3 ) )->flatten( 1 );
            $this->typo3->prefetch( $all );
            $this->prefetchFiles( $this->files->paths( $this->typo3->references(), $this->typo3->texts( $all ) ), false );
        }

        $this->currentPage = $t3Page;

        foreach( $translations as [$record, $langUid, $language, $contentRecords] )
        {
            // links in the content point to the translated pages or the ones in the fallback languages
            $chain = $this->site->chain( $langUid, $this->siteRoot( $t3Page ) );
            $translation = [
                'uid' => $langUid,
                'source' => $language['domain'] !== '' && $language['domain'] !== $sourceDomain ? $sourceDomain : '',
                'chain' => $chain,
            ];
            $typo3 = $this->typo3?->withRewriter( $this->rewriter( $translation ) );

            $content = $this->withMeta( $this->buildContent( $contentRecords, $typo3, $language['lang'] ), $record, ['uid' => $langUid, 'source' => '', 'chain' => $chain] );
            $data = array_replace( $this->buildPageData( $record, '', '', $this->translatedRedirect( $t3Page, $record, $translation ) ), [
                'tag' => $pageData['tag'] ?? Pages::TAG_PAGE,
                'lang' => $language['lang'],
            ] );

            $this->saveVariant( $page, $language, Typo3\Urls::slug( $record->slug ?? '' ), $data, $content, $variants[$language['lang']] ?? null );
        }
    }


    /**
     * Returns the redirect target of the translation of a TYPO3 external URL, shortcut or mount point page.
     *
     * Translations of external URL pages can have their own URL, the shortcut targets point to their translations.
     *
     * @param array{uid: int, source: string, chain?: array<int, int>} $translation TYPO3 language of the translation
     */
    protected function translatedRedirect( object $t3Page, object $record, array $translation ): string
    {
        $page = clone $t3Page;

        if( (int) ( $t3Page->doktype ?? 1 ) === 3 && trim( (string) ( $record->url ?? '' ) ) !== '' ) {
            $page->url = $record->url;
        }

        return $this->urls->redirect( $page, $translation );
    }


    /**
     * Returns the translations of the TYPO3 page, fetching them if they aren't prefetched with the upcoming pages.
     *
     * @return Collection<int, mixed> TYPO3 page translations ordered by UID
     */
    protected function translationRecords( object $t3Page ): Collection
    {
        if( !isset( $this->translated[$uid = (int) ( $t3Page->uid ?? 0 )] ) ) {
            return Collection::make();
        }

        return $this->translations[$uid] ??= $this->reader->translatedPages( [$uid], $this->langUids );
    }


    /**
     * Returns the existing page of the TYPO3 page if its translations are saved by the import.
     *
     * @param PageView|null $page Existing page with the URL of the TYPO3 page
     */
    protected function translatedPage( object $t3Page, ?PageView $page ): ?PageView
    {
        if( !$page || !isset( $this->translated[(int) ( $t3Page->uid ?? 0 )] ) ) {
            return null;
        }

        return $this->isImported( $page ) && !isset( $this->usedPages[$page->id ?? ''] ) && !$this->unchangedPage( $t3Page ) ? $page : null;
    }


    /**
     * Saves the translation of the page and remembers its URL for the next sibling pages.
     *
     * @param array{lang: string, domain: string} $language Language code and domain of the translation
     * @param array<string, mixed> $pageData Page data from pageData()
     * @param array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>, elementIds?: array<int, string>, meta?: array<string, mixed>} $content Content elements, file IDs, shared element IDs and meta data
     * @param PageView|false|null $existing Variant in the language including trashed ones if already loaded, NULL if there's none or FALSE to load it
     * @return PageView|null Saved variant or NULL if its URL is already used
     */
    protected function saveVariant( PageView $page, array $language, string $slug, array $pageData, array $content, PageView|false|null $existing = false ): ?PageView
    {
        if( $variant = parent::saveVariant( $page, $language, $slug, $pageData, $content, $existing ) ) {
            $this->savedPath( (string) $variant->domain, (string) $variant->path );
        }

        return $variant;
    }
}

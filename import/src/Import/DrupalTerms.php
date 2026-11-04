<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Aimeos\Cms\DB;
use Illuminate\Support\Collection;


/**
 * Collects the taxonomy terms with URL alias and renders the content of their pages.
 */
class DrupalTerms
{
    /** Maximum number of links to the newest tagged nodes on a term page */
    protected const LINK_SIZE = 100;
    /** Maximum number of tagged nodes kept per term, more than linked in case the pages of some nodes fail to import */
    protected const INDEX_SIZE = 1000;

    /** @var array<string, array{0: string, 1: string}|null> Links to the tagged nodes by language code and node ID */
    protected array $links = [];


    /**
     * @param DrupalReader $reader Reads the Drupal database
     * @param DrupalData $data Reads and caches the configuration of the Drupal database
     * @param DrupalLanguages $languages URL path prefixes of the Drupal language codes
     * @param DrupalUrls $urls Maps the Drupal nodes to the URLs of the imported pages
     * @param DrupalContent $content Renders the term descriptions as content elements
     * @param Collection<int|string, mixed> $nodes Imported Drupal nodes by node ID
     * @param array<int, array<string, string>> $translated Titles of the translations of the imported nodes by Drupal language code and node ID
     * @param \Closure(string): void $warn Shows a warning
     */
    public function __construct(
        protected DrupalReader $reader,
        protected DrupalData $data,
        protected DrupalLanguages $languages,
        protected DrupalUrls $urls,
        protected DrupalContent $content,
        protected Collection $nodes,
        protected array $translated,
        protected \Closure $warn
    ) {
    }


    /**
     * Returns the terms with URL alias of the vocabularies, their tagged nodes and their translations.
     *
     * Translations without own alias use the alias of the term with the language prefix like in Drupal.
     *
     * @param array<int, string> $vocabularies Machine names of the vocabularies
     * @return \Generator<int, array{term: \stdClass, slug: string, nids: array<int, int>, translations: array<int, array{term: \stdClass, slug: string}>}>
     */
    public function all( array $vocabularies ): \Generator
    {
        if( !$vocabularies ) {
            return;
        }

        if( !$this->data->hasTable( 'taxonomy_term_field_data' ) ) {
            ( $this->warn )( 'Table "taxonomy_term_field_data" not found, no term pages imported.' );
            return;
        }

        // the terms are processed in chunks so their data isn't kept in memory at once
        foreach( array_chunk( $this->reader->termIds( $vocabularies ), DB::IN_SIZE ) as $tids ) {
            yield from $this->chunk( $tids );
        }
    }


    /**
     * Returns the content of the term page with the description and the links to the pages of the tagged nodes.
     *
     * @param \stdClass $term Record of the taxonomy_term_field_data table
     * @param array<int, int> $nids IDs of the imported nodes tagged with the term
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>, elementIds?: array<int, string>, meta?: array<string, mixed>}
     */
    public function content( \stdClass $term, array $nids ): array
    {
        $langcode = (string) $term->langcode;

        $description = trim( (string) ( $term->description__value ?? '' ) );
        $content = $description !== '' ? $this->content->renderText( $description, (string) ( $term->description__format ?? 'basic_html' ), $langcode ) : Elements::merge();
        $this->content->removed( "term {$term->name} [{$langcode}]" );
        $links = [];

        // the term pages only link to the newest nodes, so no links beyond are built
        foreach( $nids as $nid )
        {
            if( count( $links ) >= static::LINK_SIZE ) {
                break;
            }

            if( $link = $this->link( $nid, $langcode ) ) {
                $links[] = $link;
            }
        }

        return Elements::merge( $content, $links ? $this->links( $links, $langcode ) : null );
    }


    /**
     * Returns the URL and title of the tagged node in the language of the term.
     *
     * Nodes are usually tagged with several terms, so the links are only built once.
     *
     * @param int $nid ID of the tagged node
     * @param string $langcode Drupal language code of the term
     * @return array{0: string, 1: string}|null URL and title or NULL if the node isn't imported
     */
    protected function link( int $nid, string $langcode ): ?array
    {
        $key = $langcode . ':' . $nid;

        if( array_key_exists( $key, $this->links ) ) {
            return $this->links[$key];
        }

        $node = $this->nodes->get( $nid );
        $title = $node && $langcode !== ( $node->langcode ?? '' ) ? $this->translated[$nid][$langcode] ?? null : null;
        $link = $node && ( $url = $this->urls->node( $nid, $langcode ) ) ? [$url, $title ?? (string) $node->title] : null;

        $this->links[$key] = $link;
        $this->links = Database::limit( $this->links, 10000 );

        return $link;
    }


    /**
     * Returns the list of links to the tagged nodes as HTML element.
     *
     * Plain relative URLs and titles without control characters are passed unchanged by the HTML
     * sanitizer, so the list is built directly then instead of parsing and sanitizing the HTML.
     *
     * @param array<int, array{0: string, 1: string}> $links List of URL and title pairs
     * @param string $langcode Drupal language code of the term
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}
     */
    protected function links( array $links, string $langcode ): array
    {
        $chars = '(?:[A-Za-z0-9\-._~/!$&\'()*+,;=:@?]|%[0-9A-F]{2})*';
        $html = '';

        foreach( $links as [$url, $title] )
        {
            if( !preg_match( '#^/(?!/)' . $chars . '(?:\#' . $chars . ')?$#', $url )
                || !preg_match( '/^[^\x00-\x1F\x7F-\x9F\x{FDD0}-\x{FDEF}\x{FFFE}\x{FFFF}]*$/u', $title )
            ) {
                $items = array_map( fn( $link ) => '<li><a href="' . e( $link[0] ) . '">' . e( $link[1] ) . '</a></li>', $links );
                return $this->content->html( $langcode )->elements( '<ul>' . implode( '', $items ) . '</ul>' );
            }

            $html .= '<li><a href="' . htmlspecialchars( $url, ENT_COMPAT ) . '">' . htmlspecialchars( $title, ENT_NOQUOTES ) . '</a></li>';
        }

        return ['elements' => [Elements::create( 'html', ['text' => '<ul>' . $html . '</ul>'] )], 'fileIds' => []];
    }


    /**
     * Returns the terms with URL alias, their tagged nodes and their translations.
     *
     * @param array<int, int> $tids Term IDs
     * @return \Generator<int, array{term: \stdClass, slug: string, nids: array<int, int>, translations: array<int, array{term: \stdClass, slug: string}>}>
     */
    protected function chunk( array $tids ): \Generator
    {
        $items = $ids = [];

        // only terms with URL alias get a page, so the translations and tagged nodes of the others aren't fetched
        foreach( $this->reader->terms( $tids ) as $term )
        {
            if( ( $path = trim( (string) $this->urls->termAlias( (int) $term->tid, (string) $term->langcode, true ), '/' ) ) !== '' )
            {
                $items[] = [$term, $path];
                $ids[(int) $term->tid] = (int) $term->tid;
            }
        }

        $translations = $ids ? $this->reader->termTranslations( array_values( $ids ) )->groupBy( 'tid' ) : Collection::make();
        $index = $this->index( array_values( $ids ) );

        foreach( $items as [$term, $path] )
        {
            $slug = ltrim( $this->languages->prefix( (string) $term->langcode ) . '/' . $path, '/' );
            $variants = [];

            foreach( $translations->get( $term->tid ) ?? [] as $translation )
            {
                $alias = $this->urls->termAlias( (int) $term->tid, (string) $translation->langcode );
                $variants[] = ['term' => $translation, 'slug' => ltrim( $this->languages->prefix( (string) $translation->langcode ) . '/'
                    . ( trim( (string) $alias, '/' ) ?: $path ), '/' )];
            }

            yield ['term' => $term, 'slug' => $slug, 'nids' => array_keys( $index[(int) $term->tid] ?? [] ), 'translations' => $variants];
        }
    }


    /**
     * Returns the IDs of the imported nodes tagged with the terms.
     *
     * Only the node IDs are kept instead of the index records and nodes which aren't imported are left out
     * because the term pages don't link to them. The term pages only link to the newest nodes, so only the
     * newest INDEX_SIZE index rows are fetched and not more than INDEX_SIZE node IDs are kept per term.
     *
     * @param array<int, int> $tids Term IDs
     * @return array<int, array<int, bool>> Node IDs as keys in the order of the index by term ID
     */
    protected function index( array $tids ): array
    {
        $index = [];

        if( !$tids || !$this->data->hasTable( 'taxonomy_index' ) ) {
            return $index;
        }

        // only the newest rows of each term are fetched and only the node IDs linked on the term pages are kept
        foreach( $this->reader->termIndex( $tids, static::INDEX_SIZE ) as $row )
        {
            if( count( $index[$tid = (int) $row->tid] ?? [] ) < static::INDEX_SIZE && $this->nodes->has( $nid = (int) $row->nid ) ) {
                $index[$tid][$nid] ??= true;
            }
        }

        return $index;
    }
}

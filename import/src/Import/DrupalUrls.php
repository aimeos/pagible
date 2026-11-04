<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Aimeos\Cms\Utils;
use Illuminate\Support\Collection;


/**
 * Maps the Drupal languages, nodes and terms to the URLs of the imported pages.
 */
class DrupalUrls
{
    /** Node ID of the imported Drupal front page configured in "system.site" */
    protected ?int $frontNode;

    /** @var array<int, array<string, string>> URL aliases of the imported nodes by node ID and Drupal language code, only the first one per language */
    protected array $aliases = [];
    /** @var array<int, array<string, string>>|null URL aliases of the terms imported as pages by term ID and Drupal language code, only the first one per language */
    protected ?array $termAliases = null;
    /** @var array<string, int> Node IDs by the URL aliases of the imported nodes in all languages */
    protected array $aliasNids = [];


    /**
     * Initializes the object and fetches the URL aliases of the imported nodes.
     *
     * @param DrupalReader $reader Reader for the Drupal database
     * @param DrupalData $data Configuration and entities of the Drupal database
     * @param DrupalLanguages $languages Languages and URL path prefixes of the Drupal language codes
     * @param string $lang Language code of the imported pages
     * @param Collection<int|string, mixed> $nodes Imported nodes by node ID
     * @param array<int, array<string, string>> $translated Titles of the imported node translations by Drupal language code and node ID
     * @param \Closure(): ?int $front Returns the node ID of the Drupal front page imported into the root page or NULL if none is imported
     * @param string $drupalDomain Machine name of the imported Domain Access domain or empty string for all nodes
     * @param array<int, string> $vocabularies Vocabularies whose terms are imported as pages
     */
    public function __construct(
        protected DrupalReader $reader,
        protected DrupalData $data,
        protected DrupalLanguages $languages,
        protected string $lang,
        protected Collection $nodes,
        protected array $translated,
        protected \Closure $front,
        protected string $drupalDomain = '',
        protected array $vocabularies = []
    ) {
        // only the alias strings used for the URLs and links are kept instead of all alias records
        $this->aliases = $this->reduce( $nodes->pluck( 'nid' )->map( fn( $nid ) => '/node/' . $nid )->all(), 6, true );

        $this->frontNode = $this->configuredFront();
    }


    /**
     * Returns the node ID of the Drupal front page imported into the root page or NULL if none is imported.
     */
    public function front(): ?int
    {
        return ( $this->front )();
    }


    /**
     * Returns the node ID of the imported Drupal front page configured in "system.site".
     *
     * @return int|null Node ID or NULL if no front page is configured or it isn't imported
     */
    public function frontNode(): ?int
    {
        return $this->frontNode;
    }


    /**
     * Returns the URL alias of the node in the language, without language or of any language.
     *
     * @param int $nid Node ID
     * @param string $langcode Drupal language code
     * @param bool $fallback TRUE to return the alias of another language if none exists for the language
     * @return string|null URL alias, e.g. "/about/team", or NULL if none is available
     */
    public function alias( int $nid, string $langcode, bool $fallback = false ): ?string
    {
        return $this->pick( $this->aliases[$nid] ?? [], $langcode, $fallback );
    }


    /**
     * Returns the URL alias of the term in the language, without language or of any language.
     *
     * @param int $tid Term ID of a term from the vocabularies imported as pages
     * @param string $langcode Drupal language code
     * @param bool $fallback TRUE to return the alias of another language if none exists for the language
     * @return string|null URL alias, e.g. "/tags/news", or NULL if none is available
     */
    public function termAlias( int $tid, string $langcode, bool $fallback = false ): ?string
    {
        return $this->pick( $this->termAliases()[$tid] ?? [], $langcode, $fallback );
    }


    /**
     * Returns the alias in the language, without language or of any language.
     *
     * @param array<string, string> $list URL aliases by Drupal language code in the order of the alias records
     * @param string $langcode Drupal language code
     * @param bool $fallback TRUE to return the alias of another language if none exists for the language
     * @return string|null URL alias or NULL if none is available
     */
    protected function pick( array $list, string $langcode, bool $fallback ): ?string
    {
        if( isset( $list[$langcode] ) ) {
            return $list[$langcode];
        }

        foreach( $list as $code => $alias )
        {
            if( in_array( $code, ['und', 'zxx'], true ) ) {
                return $alias;
            }
        }

        return $fallback && $list ? reset( $list ) : null;
    }


    /**
     * Returns the URL path for a Drupal node from its URL alias or title.
     *
     * The complete alias is used to keep the URLs of the Drupal site, e.g. "about/team", including
     * the language prefix if Drupal detects the language by the URL path, e.g. "de/ueber-uns".
     *
     * @param object{nid: int, title: string, langcode?: string} $node
     * @param bool $fallback TRUE to use an alias of another language if none exists for the node language
     */
    public function slug( object $node, bool $fallback = false ): string
    {
        $path = trim( $this->alias( (int) $node->nid, $node->langcode ?? '', $fallback ) ?? '', '/' );
        $prefix = $this->languages->prefix( $node->langcode ?? '' );

        return ltrim( $prefix . '/' . ( $path !== '' ? $path : Utils::slugify( $node->title ) ), '/' );
    }


    /**
     * Returns the URL of the imported node in the given language.
     *
     * Uses the translation of the node if available, otherwise the node in its default language.
     *
     * @param int $nid Node ID
     * @param string $langcode Drupal language code of the linking content
     * @return string|null URL path or NULL if the node isn't imported
     */
    public function node( int $nid, string $langcode ): ?string
    {
        $node = $this->nodes->get( $nid );

        if( !$node ) {
            return null;
        }

        $title = $langcode !== ( $node->langcode ?? '' ) ? $this->translated[$nid][$langcode] ?? null : null;

        if( $nid === $this->front() ) {
            return '/' . ( $title !== null ? $this->languages->prefix( $langcode ) : '' );
        }

        return '/' . ( $title !== null ? $this->slug( (object) ['nid' => $nid, 'langcode' => $langcode, 'title' => $title] ) : $this->slug( $node, true ) );
    }


    /**
     * Returns the URL of the page imported for the taxonomy term like importTerms() creates it.
     *
     * @param int $tid Term ID
     * @param string $langcode Drupal language code
     * @return string|null URL path or NULL if the term isn't imported as page
     */
    public function term( int $tid, string $langcode ): ?string
    {
        // terms without alias have no pages, so they aren't fetched
        if( !isset( $this->termAliases()[$tid] ) || !( $default = $this->data->entity( 'taxonomy_term_field_data', 'tid', $tid ) )
            || ( $base = trim( (string) $this->termAlias( $tid, (string) $default->langcode, true ), '/' ) ) === ''
        ) {
            return null;
        }

        $term = $this->data->entity( 'taxonomy_term_field_data', 'tid', $tid, $langcode );
        $language = $this->languages->language( $langcode );

        // translations are imported as variants with their own alias or the alias of the term
        if( $term && $term !== $default && ( $term->langcode ?? '' ) === $langcode && ( !isset( $term->status ) || $term->status )
            && $language && $language['lang'] !== $this->lang
        ) {
            $own = trim( (string) $this->termAlias( $tid, $langcode ), '/' );
            return '/' . ltrim( $this->languages->prefix( $langcode ) . '/' . ( $own ?: $base ), '/' );
        }

        return '/' . ltrim( $this->languages->prefix( (string) $default->langcode ) . '/' . $base, '/' );
    }


    /**
     * Returns the node ID of the front page configured in "system.site" as node path (e.g. "/node/1") or alias.
     *
     * @return int|null Node ID or NULL if no front page is configured or it isn't imported
     */
    protected function configuredFront(): ?int
    {
        $front = trim( (string) ( $this->data->config( 'system.site' )['page']['front'] ?? '' ), '/' );

        if( preg_match( '/^node\/(\d+)$/', $front, $m ) ) {
            $nid = (int) $m[1];
        } else {
            // the last node is used if several nodes have the same alias
            $nid = (int) $this->nodes->filter( fn( $node ) => ( $alias = $this->alias( (int) $node->nid, $node->langcode ?? '', true ) ) !== null
                && trim( $alias, '/' ) === $front )->keys()->last();
        }

        return $this->nodes->has( $nid ) ? $nid : null;
    }


    /**
     * Returns the URL aliases of the published terms of the vocabularies from the --terms option.
     *
     * @return array<int, array<string, string>> URL aliases by term ID and Drupal language code
     */
    protected function termAliases(): array
    {
        if( $this->termAliases !== null ) {
            return $this->termAliases;
        }

        $tids = $this->vocabularies && $this->data->hasTable( 'taxonomy_term_field_data' )
            ? $this->reader->termIds( $this->vocabularies )
            : [];

        return $this->termAliases = $this->reduce( array_map( fn( $tid ) => '/taxonomy/term/' . $tid, $tids ), 15 );
    }


    /**
     * Returns the first URL alias per language of the system paths.
     *
     * The alias records are fetched in chunks and only the alias strings are kept, so the records
     * of all paths aren't in memory at once.
     *
     * @param array<int, string> $paths System paths, e.g. "/node/1"
     * @param int $offset Length of the system path before the ID, e.g. 6 for "/node/"
     * @param bool $index TRUE to map all aliases to the node IDs for the links to the nodes
     * @return array<int, array<string, string>> URL aliases by entity ID and Drupal language code in the order of the alias records
     */
    protected function reduce( array $paths, int $offset, bool $index = false ): array
    {
        $result = [];

        foreach( array_chunk( $paths, \Aimeos\Cms\DB::IN_SIZE ) as $chunk )
        {
            foreach( $this->reader->aliases( $chunk, $this->drupalDomain ) as $path => $rows )
            {
                $id = (int) substr( (string) $path, $offset );

                foreach( $rows as $row )
                {
                    $alias = (string) ( $row->alias ?? '' );
                    $result[$id][(string) ( $row->langcode ?? '' )] ??= $alias;

                    if( $index ) {
                        $this->aliasNids['/' . trim( $alias, '/' )] ??= $id;
                    }
                }
            }
        }

        return $result;
    }


    /**
     * Extracts a node ID from a menu link's link__uri field.
     *
     * Supports "entity:node/42", "internal:/node/42", "route:entity.node.canonical;node=42"
     * and "internal:/<alias>" links to aliases of imported nodes.
     */
    public function linkNid( \stdClass $link ): ?int
    {
        $uri = $link->link__uri ?? '';

        if( preg_match( '/^(?:entity:node\/|internal:\/node\/|route:entity\.node\.canonical;node=)(\d+)(?:[?#;]|$)/', $uri, $m ) ) {
            return (int) $m[1];
        }

        if( str_starts_with( $uri, 'internal:/' ) )
        {
            $alias = '/' . trim( (string) strtok( substr( $uri, 9 ), '?#' ), '/' );
            return $this->aliasNids[$alias] ?? null;
        }

        return null;
    }
}

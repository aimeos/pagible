<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Reads the placements of the custom blocks in the regions of the Drupal theme and tests their visibility on the node pages.
 */
class DrupalPlacements
{
    /** @var array<int, string> Order of the regions of Drupal's core themes, other regions are sorted after them */
    protected array $regions = [
        'page_top', 'header', 'primary_menu', 'secondary_menu', 'hero', 'highlighted', 'featured_top', 'breadcrumb', 'social',
        'content_above', 'help', 'content', 'content_below', 'featured_bottom_first', 'featured_bottom_second', 'featured_bottom_third',
        'sidebar', 'sidebar_first', 'sidebar_second', 'footer_top', 'footer', 'footer_first', 'footer_second', 'footer_third',
        'footer_fourth', 'footer_fifth', 'footer_bottom', 'page_bottom',
    ];
    /** @var array<int, array{key: string, uuid: string, region: string, weight: int, visibility: array<string, mixed>, group: string, before: bool}>|null Placements of the custom blocks, sorted by region and weight */
    protected ?array $list = null;
    /** @var array<string, array{front: bool, regex: string|null}> Compiled path patterns of the request_path conditions by their patterns */
    protected array $patterns = [];


    /**
     * Initializes the object.
     *
     * @param DrupalData $data Configuration of the Drupal database
     * @param DrupalUrls $urls URL aliases and front page of the imported nodes
     * @param string $menu Machine name of the menu imported as page hierarchy from the --menu option
     * @param string $domain Machine name of the imported Domain Access domain or empty string for all nodes
     * @param \Closure(string): void $warn Shows a warning
     * @param \Closure(string, string): void $warnOnce Shows the warning of the key only once
     */
    public function __construct(
        protected DrupalData $data,
        protected DrupalUrls $urls,
        protected string $menu,
        protected string $domain,
        protected \Closure $warn,
        protected \Closure $warnOnce
    ) {
    }


    /**
     * Returns the placements of the custom blocks of the default theme.
     *
     * Pagible pages only have a main and a footer section, so blocks of the footer and sidebar
     * regions are added to the footer section. Blocks of the other regions are added to the main
     * section, before the content of the node if they are shown above the main content in Drupal,
     * e.g. in the header or highlighted region, otherwise after it.
     *
     * @return array<int, array{key: string, uuid: string, region: string, weight: int, visibility: array<string, mixed>, group: string, before: bool}>
     */
    public function all(): array
    {
        if( $this->list !== null ) {
            return $this->list;
        }

        $theme = (string) ( $this->data->config( 'system.theme' )['default'] ?? '' );
        $placements = array_filter( $this->data->configs(), fn( $config, $name ) => str_starts_with( $name, 'block.block.' )
            && ( $config['theme'] ?? '' ) === $theme && ( $config['status'] ?? true ), ARRAY_FILTER_USE_BOTH );

        // blocks in the content region are shown before the node content if their weight is lower
        $main = array_values( array_filter( $placements, fn( $config ) => ( $config['plugin'] ?? '' ) === 'system_main_block' ) )[0] ?? null;
        $blocks = $skipped = [];

        // blocks whose content is provided by the Pagible themes or pages
        $themed = ['system_main_block', 'page_title_block', 'local_tasks_block', 'local_actions_block', 'system_messages_block',
            'system_branding_block', 'system_breadcrumb_block', 'help_block', 'system_powered_by_block', 'language_block:language_interface',
            'system_menu_block:' . $this->menu];

        foreach( $placements as $name => $config )
        {
            if( !preg_match( '/^block_content:(.+)$/', $plugin = (string) ( $config['plugin'] ?? '' ), $m ) )
            {
                if( !in_array( $plugin, $themed, true ) ) {
                    $skipped[] = substr( $name, 12 ) . " ({$plugin})";
                }

                continue;
            }

            $region = (string) ( $config['region'] ?? '' );
            $weight = (int) ( $config['weight'] ?? 0 );
            $footer = (bool) preg_match( '/footer|sidebar|bottom/i', $region );

            $blocks[] = [
                'key' => substr( $name, 12 ),
                'uuid' => $m[1],
                'region' => $region,
                'weight' => $weight,
                'visibility' => (array) ( $config['visibility'] ?? [] ),
                'group' => $footer ? 'footer' : 'main',
                'before' => !$footer && ( $region === ( $main['region'] ?? 'content' )
                    ? $main !== null && $weight < (int) ( $main['weight'] ?? 0 )
                    : $this->region( $region ) < $this->region( (string) ( $main['region'] ?? 'content' ) ) ),
            ];
        }

        $this->data->prefetch( 'block_content', 'uuid', array_column( $blocks, 'uuid' ) );

        usort( $blocks, fn( $a, $b ) => [$this->region( $a['region'] ), $a['region'], $a['weight'], $a['key']]
            <=> [$this->region( $b['region'] ), $b['region'], $b['weight'], $b['key']] );

        if( $skipped ) {
            sort( $skipped );
            ( $this->warn )( 'Skipped ' . count( $skipped ) . ' blocks which are not custom blocks, add their content in Pagible if needed: ' . implode( ', ', $skipped ) );
        }

        return $this->list = $blocks;
    }


    /**
     * Tests if the block is shown on the page of the node according to its visibility conditions.
     *
     * Supports the conditions for pages, content types, languages, user roles, themes and response
     * status codes as well as Domain Access domains if --drupal-domain is used. Blocks restricted to roles are only shown if anonymous users can see them and
     * blocks restricted to other entity types aren't shown on node pages. Blocks with unknown
     * conditions aren't imported because they may only be shown on a few pages.
     *
     * @param array{key: string, visibility: array<string, mixed>} $block Block placement
     * @param object{nid: int, type: string} $node
     * @param string $langcode Drupal language code of the page
     */
    public function visible( array $block, object $node, string $langcode ): bool
    {
        foreach( $block['visibility'] as $id => $condition )
        {
            $condition = (array) $condition;

            $type = (string) ( $condition['id'] ?? $id );
            $codes = array_map( 'intval', (array) ( $condition['status_codes'] ?? [] ) );

            $result = match( $type ) {
                'request_path' => $this->matches( (string) ( $condition['pages'] ?? '' ), $node, $langcode ),
                'entity_bundle:node', 'node_type' => isset( ( (array) ( $condition['bundles'] ?? [] ) )[$node->type] ),
                'language' => isset( ( (array) ( $condition['langcodes'] ?? [] ) )[$langcode] ),
                'user_role' => isset( ( (array) ( $condition['roles'] ?? [] ) )['anonymous'] ),
                'current_theme' => ( $condition['theme'] ?? '' ) === ( $this->data->config( 'system.theme' )['default'] ?? '' ),
                'response_status' => $codes === [] || in_array( 200, $codes, true ),
                'domain' => $this->domain !== '' ? isset( ( (array) ( $condition['domains'] ?? [] ) )[$this->domain] ) : null,
                default => str_starts_with( $type, 'entity_bundle:' ) ? false : null,
            };

            if( $result === null )
            {
                ( $this->warnOnce )( "visibility:{$block['key']}", "  Skipped block {$block['key']}, its \"{$type}\" visibility condition is not supported" );
                return false;
            }

            if( $result === !empty( $condition['negate'] ) ) {
                return false;
            }
        }

        return true;
    }


    /**
     * Tests if the URL alias or system path of the node matches one of the path patterns.
     *
     * Like Drupal's request_path condition, patterns are separated by new lines, "*" is a wildcard,
     * "<front>" matches the front page and no patterns match all pages.
     *
     * @param string $pages Path patterns, e.g. "/about\n/blog/*"
     * @param object{nid: int} $node
     */
    protected function matches( string $pages, object $node, string $langcode ): bool
    {
        if( trim( $pages ) === '' ) {
            return true;
        }

        $compiled = $this->patterns[$pages] ??= $this->compile( $pages );

        if( $compiled['front'] && $node->nid === $this->urls->front() ) {
            return true;
        }

        $paths = array_filter( ['/node/' . $node->nid, mb_strtolower( $this->urls->alias( $node->nid, $langcode, true ) ?? '' )] );

        return $compiled['regex'] !== null && preg_grep( $compiled['regex'], $paths );
    }


    /**
     * Compiles the path patterns into one regular expression, so they are parsed only once for all pages.
     *
     * @param string $pages Path patterns, e.g. "/about\n/blog/*"
     * @return array{front: bool, regex: string|null} If the front page matches and the regular expression of the patterns or NULL if there are none
     */
    protected function compile( string $pages ): array
    {
        $front = false;
        $list = [];

        foreach( preg_split( '/\R/', mb_strtolower( $pages ) ) ?: [] as $pattern )
        {
            if( ( $pattern = trim( $pattern ) ) === '' ) {
                continue;
            }

            $front = $front || $pattern === '<front>';
            $list[] = str_replace( '\*', '.*', preg_quote( $pattern, '#' ) );
        }

        return ['front' => $front, 'regex' => $list ? '#^(?:' . implode( '|', $list ) . ')$#' : null];
    }


    /**
     * Returns the position of the region of Drupal's core themes, unknown regions are sorted last.
     */
    protected function region( string $region ): int
    {
        if( ( $pos = array_search( $region, $this->regions, true ) ) !== false ) {
            return (int) $pos;
        }

        // regions of other themes like "top_bar" are shown with the header regions
        return preg_match( '/^(?!.*(footer|sidebar|bottom)).*(header|top|hero|highlight|banner|breadcrumb|above|menu|nav)/i', $region )
            ? (int) array_search( 'header', $this->regions, true ) : count( $this->regions );
    }
}

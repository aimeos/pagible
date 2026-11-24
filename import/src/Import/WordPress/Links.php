<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\WordPress;


/**
 * Rewrites the links to the posts of the WordPress site to the URL paths of the imported pages.
 *
 * Supported are links by ID like "https://example.com/?p=5" and permalinks like
 * "https://example.com/2024/05/my-post/" whose last path segment is the slug of an imported post.
 * Links to the site without scheme or host like "/my-post/" and links using a former slug of a post are recognized too. If the permalinks contain
 * the post ID like "/archives/%post_id%", the posts are found by their ID. Links by slug like "/?name=my-post" and to the
 * comment pages, embeds and trackbacks of posts like "/my-post/comment-page-2/" point to the pages of the posts. Links to
 * the files, archives and feeds of the site and to other sites stay unchanged.
 */
class Links
{
    /** First path segments of the URLs which don't show posts, e.g. archives, feeds and files */
    protected const OTHER_PATHS = ['category', 'tag', 'author', 'feed', 'comments', 'page', 'search', 'wp-content', 'wp-admin', 'wp-includes', 'wp-json'];

    /** @var array<int, array{host: string, path: string}> Hosts without "www." and base paths of the site URLs */
    protected array $sites = [];
    /** @var array<string, string> URL paths of the imported pages by the slugs of their posts */
    protected array $slugs = [];
    /** @var array<string, int|string> Post IDs by the URL paths of their imported pages */
    protected array $ids = [];
    /** @var array<int, string> Paths of the category and tag archives without slashes in lower case */
    protected array $bases = [];
    /** Regular expression for the permalinks containing the post ID, NULL if the permalinks don't contain it */
    protected ?string $idPattern = null;

    /**
     * Initializes the object.
     *
     * @param array<int, string> $urls URLs of the WordPress site, e.g. the "home" and "siteurl" options
     * @param array<int|string, string> $paths URL paths of the imported pages without leading slash by post ID
     * @param (\Closure(string): void)|null $warn Shows a warning for links to posts which aren't imported
     * @param string $structure Permalink structure of the posts like "/%year%/%postname%/" from the "permalink_structure" option
     * @param array<int, string> $bases Paths of the category and tag archives from the "category_base" and "tag_base" options
     * @param array<string, string> $oldSlugs URL paths of the imported pages by the former slugs of their posts
     */
    public function __construct( array $urls, protected array $paths, protected ?\Closure $warn = null, string $structure = '', array $bases = [], array $oldSlugs = [] )
    {
        $structure = trim( $structure, '/' );

        // the other tags of the permalinks are matched by their format, only categories can contain slashes
        if( str_contains( $structure, '%post_id%' ) )
        {
            $parts = preg_split( '/(%[a-z_]+%)/', $structure, -1, PREG_SPLIT_DELIM_CAPTURE ) ?: [];
            $regex = implode( '', array_map( fn( $part ) => match( $part ) {
                '%post_id%' => '(?<id>\d+)',
                '%year%' => '\d{4}',
                '%monthnum%', '%day%', '%hour%', '%minute%', '%second%' => '\d{1,2}',
                '%category%' => '.+?',
                default => str_starts_with( $part, '%' ) && str_ends_with( $part, '%' ) && strlen( $part ) > 2 ? '[^/]+' : preg_quote( $part, '#' ),
            }, $parts ) );

            // paginated posts have the page number as last segment
            $this->idPattern = '#^' . $regex . '(?:/\d+)?$#i';
        }

        // without category and tag base, the archives use the static part of the permalinks like "/blog/category/"
        $front = trim( (string) preg_replace( '/%.*$/', '', $structure ), '/' );
        $bases = array_values( $bases ) + ['', ''];

        foreach( array_slice( $bases, 0, 2 ) as $idx => $base ) {
            $this->bases[] = mb_strtolower( trim( $base, '/' ) ?: ltrim( $front . '/' . ( $idx ? 'tag' : 'category' ), '/' ) );
        }

        foreach( $urls as $url )
        {
            if( ( $host = parse_url( $url, PHP_URL_HOST ) ) ) {
                $this->sites[] = ['host' => self::host( $host ), 'path' => rtrim( (string) parse_url( $url, PHP_URL_PATH ), '/' )];
            }
        }

        foreach( $paths as $id => $path )
        {
            $this->slugs[mb_strtolower( $path )] ??= $path;
            $this->ids[$path] ??= $id;
        }

        // WordPress redirects the former slugs of posts only if no post uses them now
        foreach( $oldSlugs as $slug => $path ) {
            $this->slugs[mb_strtolower( (string) $slug )] ??= $path;
        }
    }


    /**
     * Rewrites the links of the HTML to the posts of the site.
     *
     * Links to posts which aren't imported, e.g. the ones of pages or drafts, stay unchanged and are reported.
     *
     * @param string $html HTML with links
     * @return string HTML with rewritten links
     */
    public function rewrite( string $html ): string
    {
        if( !str_contains( $html, 'href' ) ) {
            return $html;
        }

        $missing = [];
        $result = preg_replace_callback( '/(\shref=)(["\'])(.*?)\2/is', function( $m ) use ( &$missing ) {
            $href = Html::decode( $m[3] );

            if( ( $local = $this->local( $href ) ) === null ) {
                return $m[0];
            }

            if( ( $path = $this->path( $local ) ) === false ) {
                return $m[0];
            }

            if( $path === null )
            {
                $missing[$href] = true;
                return $m[0];
            }

            $fragment = $this->fragment( (string) parse_url( $local, PHP_URL_FRAGMENT ), $path );
            return $m[1] . $m[2] . e( '/' . $path . ( $fragment !== '' ? '#' . $fragment : '' ) ) . $m[2];
        }, $html );

        if( $missing && $this->warn ) {
            ( $this->warn )( '  Links to posts which aren\'t imported kept: ' . implode( ', ', array_keys( $missing ) ) );
        }

        return $result ?? $html;
    }


    /**
     * Returns the canonical URL for the imported pages.
     *
     * Canonical URLs to imported posts of the site point to their pages, the ones to other sites stay unchanged.
     * Canonical URLs to other pages of the site, e.g. archives or posts which aren't imported, would refer to
     * the WordPress site, so they are left out.
     *
     * @param string $url Decoded canonical URL from the SEO plugins
     * @return string|null URL path of the imported page, the URL of the other site or NULL if it's left out
     */
    public function canonical( string $url ): ?string
    {
        if( ( $local = $this->local( $url ) ) === null ) {
            return $url;
        }

        return is_string( $path = $this->path( $local ) ) ? '/' . $path : null;
    }


    /**
     * Returns the fragment of the link pointing to the element of the imported page with the HTML anchor.
     *
     * The fragments added by WordPress itself like "#comments", "#respond", "#comment-12" and "#more-5" stay unchanged.
     *
     * @param string $fragment Fragment of the link without "#"
     * @param string $path URL path of the imported page without leading slash
     * @return string Element ID or unchanged fragment
     */
    protected function fragment( string $fragment, string $path ): string
    {
        if( $fragment === '' || preg_match( '/^(?:comments|respond|comment-\d+|more-\d+)$/', $fragment ) || !isset( $this->ids[$path] ) ) {
            return $fragment;
        }

        return Html::anchorId( $this->ids[$path], rawurldecode( $fragment ) );
    }


    /**
     * Returns the host name without "www." in lower case, so links with and without "www." are recognized.
     */
    protected static function host( string $host ): string
    {
        return (string) preg_replace( '/^www\./', '', strtolower( $host ) );
    }


    /**
     * Returns the URL relative to the base path of the site if it links to the site.
     *
     * @param string $href Decoded URL of the link
     * @return string|null URL starting with "/" relative to the site or NULL for links to other sites and relative links
     */
    protected function local( string $href ): ?string
    {
        if( preg_match( '#^(?:https?:)?//([^/?\#:]+)(?::\d+)?(.*)$#is', $href, $m ) )
        {
            $host = self::host( $m[1] );
            $path = '/' . ltrim( $m[2], '/' );

            foreach( $this->sites as $site )
            {
                if( $site['host'] === $host && ( $site['path'] === '' || $path === $site['path'] || preg_match( '#^' . preg_quote( $site['path'], '#' ) . '(?:[/?\#]|$)#i', $path ) ) ) {
                    return '/' . ltrim( substr( $path, strlen( $site['path'] ) ), '/' );
                }
            }

            return null;
        }

        if( !str_starts_with( $href, '/' ) ) {
            return null;
        }

        // root-relative links of subdirectory installations contain the base path
        foreach( $this->sites as $site )
        {
            if( $site['path'] !== '' && preg_match( '#^' . preg_quote( $site['path'], '#' ) . '(?:[/?\#]|$)#i', $href ) ) {
                return '/' . ltrim( substr( $href, strlen( $site['path'] ) ), '/' );
            }
        }

        return $href;
    }


    /**
     * Returns the URL path of the imported page the link relative to the site points to.
     *
     * @param string $local URL relative to the site starting with "/"
     * @return string|false|null URL path without leading slash, NULL if the post isn't imported or FALSE if the link isn't to a post
     */
    protected function path( string $local ): string|false|null
    {
        $path = trim( (string) parse_url( $local, PHP_URL_PATH ), '/' );
        parse_str( (string) parse_url( $local, PHP_URL_QUERY ), $query );

        // links by ID like "/?p=5" or "/index.php?page_id=7", only posts are imported
        foreach( ['p', 'page_id'] as $name )
        {
            if( in_array( $path, ['', 'index.php'], true ) && is_string( $id = $query[$name] ?? null ) && ctype_digit( $id ) ) {
                return $this->paths[(int) $id] ?? null;
            }
        }

        // links by slug like "/?name=my-post"
        if( in_array( $path, ['', 'index.php'], true ) && is_string( $name = $query['name'] ?? null ) && $name !== '' ) {
            return $this->slugs[mb_strtolower( trim( $name, '/' ) )] ?? null;
        }

        // feeds of the comments of posts like "/my-post/feed/atom/" aren't available
        if( preg_match( '#(?:^|/)feed(?:/(?:feed|rdf|rss|rss2|atom))?$#i', $path ) ) {
            return false;
        }

        // the endpoints of the posts like "/my-post/comment-page-2/", "/my-post/embed/" and "/my-post/trackback/" show the post
        $path = (string) preg_replace( '#/(?:comment-page-\d+|embed|trackback)$#i', '', $path );
        $segments = explode( '/', $path );
        $lower = mb_strtolower( rawurldecode( $path ) );

        // the home page, archives, feeds and files aren't posts
        if( $path === '' || in_array( strtolower( $segments[0] ), self::OTHER_PATHS, true ) || preg_match( '/\.[a-z0-9]{2,5}$/i', $path )
            || array_filter( $this->bases, fn( $base ) => str_starts_with( $lower . '/', $base . '/' ) ) )
        {
            return false;
        }

        // permalinks containing the post ID like "/archives/123"
        if( $this->idPattern && preg_match( $this->idPattern, rawurldecode( $path ), $m ) ) {
            return $this->paths[(int) $m['id']] ?? null;
        }

        $slug = mb_strtolower( rawurldecode( (string) end( $segments ) ) );

        // paginated posts have the page number as last segment like "/my-post/2/", date archives only numbers like "/2024/05/"
        if( ctype_digit( $slug ) && !isset( $this->slugs[$slug] ) )
        {
            $prev = count( $segments ) > 1 ? mb_strtolower( rawurldecode( $segments[count( $segments ) - 2] ) ) : '';
            return $this->slugs[$prev] ?? ( ctype_digit( $prev ) || $prev === '' ? false : null );
        }

        return $this->slugs[$slug] ?? null;
    }
}

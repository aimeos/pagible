<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Applies the filters of the Drupal text formats which change the HTML structure and rewrites
 * the links to nodes, terms and files.
 */
class DrupalFilters
{
    /** @var array<string, array<int, string>> Enabled filters of Drupal's standard text formats used without config table */
    protected array $formats = [
        'plain_text' => ['filter_html_escape', 'filter_autop', 'filter_url'],
        'restricted_html' => ['filter_html', 'filter_autop', 'filter_url'],
    ];


    /**
     * @param DrupalData $data Configuration of the Drupal database
     * @param DrupalLanguages $languages Languages of the imported pages
     * @param DrupalUrls $urls URLs of the imported pages
     * @param DrupalSite $site URLs of the Drupal site
     */
    public function __construct( protected DrupalData $data, protected DrupalLanguages $languages, protected DrupalUrls $urls, protected DrupalSite $site )
    {
    }


    /**
     * Applies the filters of the text format and rewrites the links to the URLs of the imported pages.
     *
     * @param string $text Text of the field
     * @param string $format Text format ID, e.g. "basic_html"
     * @param string $langcode Drupal language code of the content
     */
    public function apply( string $text, string $format, string $langcode ): string
    {
        return $this->rewriteLinks( $this->filterText( $text, $format ), $langcode );
    }


    /**
     * Wraps the text blocks separated by empty lines in paragraphs like Drupal's filter_autop.
     */
    protected function autop( string $html ): string
    {
        $blocks = 'address|article|aside|blockquote|dd|div|dl|dt|drupal-entity|drupal-media|figure|footer|h[1-6]|header|hr|li|ol|p|pre|section|table|ul';
        $paras = preg_split( '/\n\s*\n/', $html ) ?: [];

        return implode( "\n\n", array_map( function( $para ) use ( $blocks ) {
            $para = trim( $para );
            return $para === '' || preg_match( '/^<(?:' . $blocks . ')\b/i', $para ) ? $para : '<p>' . nl2br( $para, false ) . '</p>';
        }, $paras ) );
    }


    /**
     * Returns the tags allowed by the filter_html filter of the Drupal text format.
     *
     * @param string $format Text format ID, e.g. "basic_html"
     * @return string Allowed tags with attributes, e.g. "<a href> <em>"
     */
    protected function allowedHtml( string $format ): string
    {
        $filters = (array) ( $this->data->config( 'filter.format.' . $format )['filters'] ?? [] );
        $settings = (array) ( ( (array) ( $filters['filter_html'] ?? [] ) )['settings'] ?? [] );
        $allowed = $settings['allowed_html'] ?? '';

        return is_string( $allowed ) ? $allowed : '';
    }


    /**
     * Returns the enabled filters of the Drupal text format.
     *
     * @param string $format Text format ID, e.g. "basic_html"
     * @return array<int, string> Filter IDs
     */
    protected function filters( string $format ): array
    {
        if( !( $config = $this->data->config( 'filter.format.' . $format ) ) ) {
            return $this->formats[$format] ?? [];
        }

        $filters = array_filter( (array) ( $config['filters'] ?? [] ), fn( $filter ) => !empty( ( (array) $filter )['status'] ) );

        return array_map( 'strval', array_keys( $filters ) );
    }


    /**
     * Applies the filters of the Drupal text format which change the HTML structure.
     *
     * Supports filter_html_escape, the iframes of filter_html, filter_autop and filter_url, the captions of filter_caption
     * are added when the HTML is split into content elements.
     *
     * @param string $text Text of the field
     * @param string $format Text format ID, e.g. "basic_html"
     */
    protected function filterText( string $text, string $format ): string
    {
        $filters = $this->filters( $format );
        $text = str_replace( "\r\n", "\n", trim( $text ) );

        if( in_array( 'filter_html_escape', $filters, true ) ) {
            $text = e( $text );
        }

        // iframes are created again from their URLs, so they are removed like by Drupal if the text format doesn't allow them
        if( in_array( 'filter_html', $filters, true ) && !preg_match( '/<iframe[\s>]/i', $this->allowedHtml( $format ) ) ) {
            $text = preg_replace( '#</?iframe\b[^>]*>#i', '', $text ) ?? $text;
        }

        if( in_array( 'filter_autop', $filters, true ) ) {
            $text = $this->autop( $text );
        }

        if( in_array( 'filter_url', $filters, true ) ) {
            $text = $this->linkifyHtml( $text );
        }

        return $text;
    }


    /**
     * Converts URLs and e-mail addresses in escaped text to links like Drupal's filter_url.
     */
    protected function linkify( string $text ): string
    {
        $pattern = '/(?<![\w@\/.])(?:((?:https?|ftp):\/\/|www\.)(?:(?!&quot;|&#039;|&lt;|&gt;)[^\s<>"])+|[\w.+-]+@[\w-]+(?:\.[\w-]+)+)/i';

        return (string) preg_replace_callback( $pattern, function( $m ) {
            $url = $m[0];
            $trail = '';

            // trailing punctuation and unbalanced closing brackets are not part of the URL
            while( preg_match( '/[.,;:!?\)\]]$/', $url ) && !( str_ends_with( $url, ')' ) && substr_count( $url, '(' ) >= substr_count( $url, ')' ) ) ) {
                $trail = substr( $url, -1 ) . $trail;
                $url = substr( $url, 0, -1 );
            }

            // e() escapes "&" which must stay escaped in the href and link text
            $href = match( true ) {
                !empty( $m[1] ) && strtolower( $m[1] ) === 'www.' => 'http://' . $url,
                !empty( $m[1] ) => $url,
                default => 'mailto:' . $url,
            };

            return '<a href="' . $href . '">' . $url . '</a>' . $trail;
        }, $text );
    }


    /**
     * Converts URLs and e-mail addresses in the text of HTML to links, except in links and code.
     */
    protected function linkifyHtml( string $html ): string
    {
        $parts = preg_split( '/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE ) ?: [];
        $skip = 0;

        foreach( $parts as $idx => $part )
        {
            if( preg_match( '/^<(\/?)(a|code|pre|script|style)\b/i', $part, $m ) ) {
                $skip = max( 0, $skip + ( $m[1] === '/' ? -1 : 1 ) );
            } elseif( $skip === 0 && !str_starts_with( $part, '<' ) ) {
                $parts[$idx] = $this->linkify( $part );
            }
        }

        return implode( '', $parts );
    }


    /**
     * Replaces links to "/node/{nid}" by the URLs of the imported pages, links to "/taxonomy/term/{tid}" by
     * the URLs of the terms and links to files by their URLs.
     *
     * Supports links with the site URL (also with another scheme or host name like "www."), the path
     * of subdirectory installations and language prefixes, e.g. "https://example.com/drupal/de/node/42".
     * Links to files become absolute URLs of the Drupal site which keep working on the new domain.
     * Other links and links to nodes which aren't imported stay unchanged.
     *
     * @param string $html HTML with links
     * @param string $langcode Drupal language code of the content
     */
    protected function rewriteLinks( string $html, string $langcode ): string
    {
        $url = (array) ( $this->data->config( 'language.negotiation' )['url'] ?? [] );
        $prefixes = array_filter( array_map( fn( $prefix ) => trim( (string) $prefix, '/' ), (array) ( $url['prefixes'] ?? [] ) ) );
        $basePath = rtrim( (string) parse_url( $this->site->base(), PHP_URL_PATH ), '/' );

        return (string) preg_replace_callback( '/(\shref=)(["\'])(.*?)\2/is', function( $m ) use ( $prefixes, $basePath, $langcode ) {
            $href = $orig = html_entity_decode( $m[3], ENT_QUOTES, 'UTF-8' );
            $local = true;

            if( preg_match( '#^(?:https?:)?//([^/?\#]+)(.*)$#is', $href, $parts ) )
            {
                if( !$this->site->host( $parts[1] ) ) {
                    return $m[0];
                }

                // root-relative links only work for links to the domain of the content itself
                $local = $this->site->host( $parts[1], $this->languages->language( $langcode )['domain'] ?? '' );
                $href = '/' . ltrim( $parts[2], '/' );
            }

            if( !str_starts_with( $href, '/' ) ) {
                return $m[0];
            }

            if( $basePath !== '' && ( $href === $basePath || str_starts_with( $href, $basePath . '/' ) ) ) {
                $href = '/' . ltrim( substr( $href, strlen( $basePath ) ), '/' );
            }

            if( preg_match( '#^/(?:sites/[^/]+/files|system/files)/#i', $href ) ) {
                return ( $base = $this->site->base() ) && $base . $href !== $orig ? $m[1] . $m[2] . e( $base . $href ) . $m[2] : $m[0];
            }

            if( !$local ) {
                return $m[0];
            }

            if( !preg_match( '#^/(?:([^/?\#]+)/)?(node|taxonomy/term)/(\d+)(?=[?\#]|$)(.*)$#is', $href, $node ) || $node[1] !== '' && !in_array( $node[1], $prefixes, true ) ) {
                return $m[0];
            }

            $langcode = $node[1] !== '' ? (string) array_search( $node[1], $prefixes, true ) : $langcode;
            $path = $node[2] === 'node' ? $this->urls->node( (int) $node[3], $langcode ) : $this->urls->term( (int) $node[3], $langcode );

            return $path ? $m[1] . $m[2] . e( $path . $node[4] ) . $m[2] : $m[0];
        }, $html );
    }
}

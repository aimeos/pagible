<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Rewrites the HTML of TYPO3 content elements.
 *
 * Files referenced in the HTML are imported and links to TYPO3 pages are rewritten to their imported counterparts.
 */
class Typo3Html
{
    /**
     * Initializes the object.
     *
     * @param \Closure(string): ?array{id: string, url: string} $url Imports the fileadmin file of the URL and returns its file ID and new URL
     * @param \Closure(int, string): ?string $page Returns the URL of the TYPO3 page by UID including the fragment or NULL if it isn't imported
     */
    public function __construct( protected \Closure $url, protected \Closure $page )
    {
    }


    /**
     * Imports the TYPO3 files referenced by HTML attributes and rewrites their URLs and the links to TYPO3 pages.
     *
     * @return array{html: string, fileIds: array<int, string>}
     */
    public function rewrite( string $html ): array
    {
        $html = $this->promoteLazyAttributes( $this->rewriteTypo3Links( $html ) );
        $fileIds = [];

        $replace = function( string $url ) use ( &$fileIds ): array {
            $path = parse_url( html_entity_decode( trim( $url ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ), PHP_URL_PATH );

            if( !is_string( $path ) || !preg_match( '#(?:^|/)fileadmin/.+#i', $path ) ) {
                return ['url' => null, 'skipped' => false];
            }

            if( !( $file = ( $this->url )( $url ) ) ) {
                return ['url' => null, 'skipped' => true];
            }

            $fileIds[] = $file['id'];

            return ['url' => htmlspecialchars( $file['url'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ), 'skipped' => false];
        };

        $html = (string) preg_replace_callback( '/\b(srcset)\s*=\s*(["\'])(.*?)\2/is', function( array $matches ) use ( $replace ): string {
            $candidates = explode( ',', $matches[3] );

            foreach( $candidates as &$candidate )
            {
                $parts = preg_split( '/\s+/', trim( $candidate ), 2 );
                $replacement = $replace( $parts[0] ?? '' );

                if( $replacement['url'] !== null ) {
                    $candidate = $replacement['url'] . ( isset( $parts[1] ) ? ' ' . $parts[1] : '' );
                } elseif( $replacement['skipped'] ) {
                    $candidate = '';
                }
            }

            $candidates = array_values( array_filter( array_map( 'trim', $candidates ) ) );

            return $candidates ? $matches[1] . '=' . $matches[2] . implode( ', ', $candidates ) . $matches[2] : '';
        }, $html );

        $html = (string) preg_replace_callback( '/\b(src|href|poster|data-src)\s*=\s*(["\'])(.*?)\2/is', function( array $matches ) use ( $replace ): string {
            $replacement = $replace( $matches[3] );
            return $replacement['skipped'] ? '' : $matches[1] . '=' . $matches[2] . ( $replacement['url'] ?? $matches[3] ) . $matches[2];
        }, $html );

        $html = (string) preg_replace_callback( '/\b(src|href|poster|data-src)\s*=\s*([^\s"\'=<>`]+)/is', function( array $matches ) use ( $replace ): string {
            $replacement = $replace( $matches[2] );
            return $replacement['skipped'] ? '' : $matches[1] . '=' . ( $replacement['url'] ?? $matches[2] );
        }, $html );

        // empty media placeholders are removed if their only source was rejected, linked text and videos with nested <source> elements stay
        $html = (string) preg_replace( '/<(?:img|source)\b(?![^>]*\b(?:src|srcset)\s*=)[^>]*>/is', '', $html );

        return ['html' => $html, 'fileIds' => array_values( array_unique( $fileIds ) )];
    }


    /**
     * Promotes TYPO3 lazy-loading attributes before HTMLPurifier removes them.
     */
    protected function promoteLazyAttributes( string $html ): string
    {
        return (string) preg_replace_callback( '/<(img|source|video)\b[^>]*>/is', function( array $matches ): string {
            $tag = $matches[0];

            foreach( ['srcset', 'src'] as $name )
            {
                if( preg_match( '/\sdata-' . $name . '\s*=\s*(["\'])(.*?)\1/is', $tag ) )
                {
                    $tag = preg_match( '/\s' . $name . '\s*=/i', $tag )
                        ? (string) preg_replace( '/\sdata-' . $name . '\s*=\s*(["\']).*?\1/is', '', $tag )
                        : (string) preg_replace( '/\sdata-' . $name . '(\s*=)/i', ' ' . $name . '$1', $tag, 1 );
                }
            }

            return $tag;
        }, $html );
    }


    /**
     * Resolves a legacy TYPO3 link target such as "61#c381".
     */
    protected function resolveTypo3Target( string $target ): ?string
    {
        if( preg_match( '#^(?:https?://|mailto:|tel:|/)#i', $target ) ) {
            return $target;
        }

        if( !preg_match( '/^(\d+)(?:#(.+))?$/', $target, $matches ) ) {
            return null;
        }

        return ( $this->page )( (int) $matches[1], $matches[2] ?? '' );
    }


    /**
     * Converts legacy TYPO3 link tags and t3:// page URLs to portable links.
     */
    protected function rewriteTypo3Links( string $html ): string
    {
        $html = (string) preg_replace_callback( '/<link\s+([^>]+)>(.*?)<\/link>/is', function( array $matches ): string {
            $tokens = array_values( array_filter( str_getcsv( trim( $matches[1] ), ' ', '"', '\\' ), fn( $value ) => $value !== '' ) );

            if( !( $url = isset( $tokens[0] ) ? $this->resolveTypo3Target( (string) $tokens[0] ) : null ) ) {
                return $matches[2];
            }

            $attributes = ' href="' . htmlspecialchars( $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) . '"';

            if( in_array( $tokens[1] ?? '', ['_blank', '_self'], true ) ) {
                $attributes .= ' target="' . $tokens[1] . '"';
            }

            if( !empty( $tokens[2] ) && $tokens[2] !== '-' ) {
                $attributes .= ' class="' . htmlspecialchars( (string) $tokens[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) . '"';
            }

            if( !empty( $tokens[3] ) && $tokens[3] !== '-' ) {
                $attributes .= ' title="' . htmlspecialchars( (string) $tokens[3], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) . '"';
            }

            return '<a' . $attributes . '>' . $matches[2] . '</a>';
        }, $html );

        return (string) preg_replace_callback( '/\bhref\s*=\s*(["\'])(t3:\/\/page\?.*?)\1/is', function( array $matches ): string {
            $url = $this->rewriteTypo3Url( html_entity_decode( $matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
            return $url ? 'href=' . $matches[1] . htmlspecialchars( $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) . $matches[1] : $matches[0];
        }, $html );
    }


    /**
     * Rewrites one t3://page URL if its target page is part of the import.
     */
    protected function rewriteTypo3Url( string $url ): ?string
    {
        if( !preg_match( '~^t3://page\?([^#]+)(?:#(.*))?$~i', trim( $url ), $matches ) ) {
            return null;
        }

        parse_str( html_entity_decode( $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ), $query );
        $uid = (int) ( $query['uid'] ?? 0 );
        $fragment = $query['fragment'] ?? $matches[2] ?? '';

        return $uid > 0 ? ( $this->page )( $uid, is_string( $fragment ) ? $fragment : '' ) : null;
    }
}

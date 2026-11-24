<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Typo3;


/**
 * Rewrites the HTML of TYPO3 content elements.
 *
 * Files referenced in the HTML are imported and links to TYPO3 pages are rewritten to their imported counterparts.
 */
class Html
{
    /** @var array<int, string> Block elements which aren't wrapped into paragraphs by paragraphs() */
    protected const BLOCKS = ['address', 'article', 'aside', 'blockquote', 'details', 'dd', 'div', 'dl', 'dt', 'fieldset', 'figcaption',
        'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hr', 'li', 'main', 'nav', 'ol', 'p', 'pre', 'section',
        'summary', 'table', 'ul', 'script', 'style'];

    /**
     * Initializes the object.
     *
     * @param \Closure(string): ?array{id: string, url: string} $url Imports the TYPO3 file of the URL and returns its file ID and new URL
     * @param \Closure(int, string, ?int): ?string $page Returns the URL of the TYPO3 page by UID (0 for the page whose content is imported) including the fragment
     *  in the TYPO3 language passed as third parameter (NULL for the language of the content) or NULL if it isn't imported
     * @param (\Closure(int): ?array{id: string, url: string})|null $file Imports the TYPO3 file by its sys_file UID and returns its file ID and new URL
     * @param string $pattern Regular expression matching the paths of the URLs of the TYPO3 files
     * @param (\Closure(int, string): ?array{id: string, url: string})|null $identifier Imports the TYPO3 file by its storage UID and identifier and returns its file ID and new URL
     */
    public function __construct( protected \Closure $url, protected \Closure $page, protected ?\Closure $file = null,
        protected string $pattern = '#(?:^|/)fileadmin/.+#i', protected ?\Closure $identifier = null )
    {
    }


    /** @var array<int, string> IDs of the files imported by their sys_file UID while the HTML is rewritten */
    protected array $fileIds = [];


    /**
     * Imports the TYPO3 files referenced by HTML attributes and rewrites their URLs and the links to TYPO3 pages.
     *
     * @return array{html: string, fileIds: array<int, string>}
     */
    public function rewrite( string $html ): array
    {
        $this->fileIds = [];
        $html = $this->promoteLazyAttributes( $this->rewriteFileUids( $this->rewriteTypo3Links( $html ) ) );

        // links to the content anchors of the same page like "#c123" point to the IDs of their elements
        $html = (string) preg_replace_callback( '/\bhref\s*=\s*(["\'])#(c\d+)\1/i', fn( array $matches ) => 'href=' . $matches[1] . '#' . Urls::fragment( $matches[2] ) . $matches[1], $html );
        $fileIds = $this->fileIds;

        $replace = function( string $url ) use ( &$fileIds ): array {
            $path = parse_url( html_entity_decode( trim( $url ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ), PHP_URL_PATH );

            if( !is_string( $path ) || !preg_match( $this->pattern, $path ) ) {
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
     * Wraps the top-level lines of the RTE text which aren't inside block elements into paragraphs.
     *
     * Emulates "encapsLines" of TYPO3's lib.parseFunc_RTE: each line of the text outside of block elements
     * becomes a paragraph, block elements like <p>, <ul> or <table> are kept as they are. Empty lines
     * are dropped instead of being turned into empty paragraphs. Must be applied after rewrite() because
     * legacy <link> tags would be treated like void elements otherwise.
     */
    public static function paragraphs( string $html ): string
    {
        $tokens = preg_split( '/(<!--.*?-->|<\/?[a-zA-Z][a-zA-Z0-9]*\b[^>]*>)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY ) ?: [];
        $out = $line = $block = '';
        $name = null;
        $depth = 0;

        $flush = function() use ( &$out, &$line ): void {
            $content = strip_tags( $line, '<img><iframe><video><audio><picture><object><embed><svg>' );

            if( trim( str_replace( ['&nbsp;', '&#160;', "\u{00A0}"], '', $content ) ) !== '' ) {
                $out .= '<p>' . trim( $line ) . '</p>';
            } elseif( trim( $line ) === '' ) {
                $out .= $line;
            }

            $line = '';
        };

        foreach( $tokens as $token )
        {
            $tag = preg_match( '/^<(\/?)([a-zA-Z][a-zA-Z0-9]*)/', $token, $match ) ? strtolower( $match[2] ) : null;

            // inside of a top-level block element, its content is kept as it is
            if( $name !== null )
            {
                $block .= $token;

                if( $tag === $name && !str_ends_with( $token, '/>' ) ) {
                    $depth += $match[1] === '/' ? -1 : 1;
                }

                if( $depth === 0 )
                {
                    $out .= $block;
                    $block = '';
                    $name = null;
                }

                continue;
            }

            if( $tag !== null && in_array( $tag, self::BLOCKS, true ) )
            {
                $flush();

                // void and stray closing block tags are kept as they are
                if( $match[1] === '/' || in_array( $tag, ['hr'], true ) || str_ends_with( $token, '/>' ) ) {
                    $out .= $token;
                } else {
                    $name = $tag;
                    $block = $token;
                    $depth = 1;
                }

                continue;
            }

            if( $tag !== null || str_starts_with( $token, '<!--' ) )
            {
                $line .= $token;
                continue;
            }

            $parts = preg_split( '/(\r\n|\r|\n)/', $token, -1, PREG_SPLIT_DELIM_CAPTURE ) ?: [];

            foreach( $parts as $idx => $part )
            {
                if( $idx % 2 === 0 ) {
                    $line .= $part;
                } else {
                    $flush();
                    $out .= $part;
                }
            }
        }

        // unclosed block elements are kept as they are
        $flush();

        return $out . $block;
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
     * Returns the URL of the first part of a TYPO3 link field like "t3://page?uid=5 _blank" or an empty string if it can't be resolved.
     *
     * Legacy notations like "5", "file:12" or "info@example.com" are resolved like TYPO3's LinkService does.
     */
    public function href( string $link ): string
    {
        if( ( $link = self::typolink( $link ) ) === '' ) {
            return '';
        }

        $html = $this->rewrite( '<a href="' . htmlspecialchars( $link, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) . '">x</a>' )['html'];

        return preg_match( '/<a\s[^>]*href="([^"]*)"/i', $html, $match ) ? html_entity_decode( $match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : '';
    }


    /**
     * Converts the first part of a TYPO3 link field in legacy notation to a t3://, mailto: or https:// URL.
     *
     * Page UIDs with optional page type and fragment like "5", "5,3" or "5#c3", file references like "file:12", e-mail
     * addresses, host names starting with "www." and paths to "fileadmin/" or "uploads/" are converted. Links with
     * other schemes than http, https, mailto, tel and t3, e.g. "javascript:", return an empty string. The language
     * of the additional parameters ("&L=1") is added to page links.
     *
     * @param string $value Value of the TYPO3 link field like "5 _blank - \"Title\""
     * @return string Converted URL or an empty string
     */
    public static function typolink( string $value ): string
    {
        $link = self::legacy( preg_split( '/\s+/', trim( $value ) )[0] ?? '' );

        // the language of the additional parameters, the fifth part like in "t3://page?uid=5 - - - &L=1", is added to page links
        if( str_starts_with( strtolower( $link ), 't3://page?' ) && ( $lang = self::language( $value ) ) !== null && !preg_match( '/[?&](?:L|_language)=/', $link ) )
        {
            $parts = explode( '#', $link, 2 );
            $link = $parts[0] . '&L=' . $lang . ( isset( $parts[1] ) ? '#' . $parts[1] : '' );
        }

        return $link;
    }


    /**
     * Returns the TYPO3 language UID of the additional parameters of the TYPO3 link field or NULL if there's none.
     *
     * @param string $value Value of the TYPO3 link field like "t3://page?uid=5 - - - &L=1"
     */
    protected static function language( string $value ): ?int
    {
        $tokens = array_values( array_filter( str_getcsv( trim( $value ), ' ', '"', '\\' ), fn( $token ) => $token !== '' ) );
        parse_str( ltrim( (string) ( $tokens[4] ?? '' ), '&' ), $params );
        $lang = $params['L'] ?? $params['_language'] ?? null;

        return is_scalar( $lang ) && preg_match( '/^\d+$/', (string) $lang ) ? (int) $lang : null;
    }


    /**
     * Converts the URL of a TYPO3 link field in legacy notation, see typolink().
     */
    protected static function legacy( string $link ): string
    {
        if( preg_match( '/^(\d+)(?:,\d+)?(?:#(.*))?$/', $link, $matches ) ) {
            return 't3://page?uid=' . $matches[1] . ( ( $matches[2] ?? '' ) !== '' ? '#' . $matches[2] : '' );
        }

        if( preg_match( '/^file:(\d+)$/i', $link, $matches ) ) {
            return 't3://file?uid=' . $matches[1];
        }

        if( preg_match( '#^(?:fileadmin|uploads)/#i', $link ) ) {
            return '/' . $link;
        }

        if( filter_var( $link, FILTER_VALIDATE_EMAIL ) ) {
            return 'mailto:' . $link;
        }

        if( preg_match( '#^www\.[\w-]+(?:\.[\w-]+)+#i', $link ) ) {
            return 'https://' . $link;
        }

        if( preg_match( '#^([a-z][a-z0-9+.-]*):#i', $link, $matches ) && !in_array( strtolower( $matches[1] ), ['http', 'https', 'mailto', 'tel', 't3'], true ) ) {
            return '';
        }

        return $link;
    }


    /**
     * Resolves a legacy TYPO3 link target such as "61#c381".
     */
    protected function resolveTypo3Target( string $target ): ?string
    {
        $link = self::typolink( $target );

        if( preg_match( '#^t3://#i', $link ) ) {
            return $this->rewriteTypo3Url( $link );
        }

        return preg_match( '#^(?:https?://|mailto:|tel:|/)#i', $link ) ? $link : null;
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

        // links to TYPO3 records which can't be resolved, e.g. t3://record?identifier=news&uid=1, are removed and their text is kept
        $html = (string) preg_replace_callback( '/<a\b([^>]*?)\bhref\s*=\s*(["\'])(t3:\/\/[^"\']*)\2([^>]*)>(.*?)<\/a>/is', function( array $matches ): string {
            if( !( $url = $this->rewriteTypo3Url( html_entity_decode( $matches[3], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ) ) {
                return $matches[5];
            }

            return '<a' . $matches[1] . 'href=' . $matches[2] . htmlspecialchars( $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) . $matches[2] . $matches[4] . '>' . $matches[5] . '</a>';
        }, $html );

        return (string) preg_replace_callback( '/\bhref\s*=\s*(["\'])(t3:\/\/(?:page|file|url)\?.*?)\1/is', function( array $matches ): string {
            $url = $this->rewriteTypo3Url( html_entity_decode( $matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
            return $url ? 'href=' . $matches[1] . htmlspecialchars( $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) . $matches[1] : $matches[0];
        }, $html );
    }


    /**
     * Rewrites one t3://page, t3://file or t3://url URL if its target is part of the import.
     *
     * Other link types like t3://record or t3://folder can't be resolved and return NULL.
     */
    protected function rewriteTypo3Url( string $url ): ?string
    {
        if( !preg_match( '~^t3://(page|file|url)\?([^#]+)(?:#(.*))?$~i', trim( $url ), $matches ) ) {
            return null;
        }

        parse_str( html_entity_decode( $matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' ), $query );

        if( strtolower( $matches[1] ) === 'url' ) {
            return is_string( $link = $query['url'] ?? null ) && preg_match( '#^(?:https?://|mailto:|tel:)#i', $link ) ? $link : null;
        }

        // links to the current page use "uid=current"
        $current = strtolower( $matches[1] ) === 'page' && ( $query['uid'] ?? null ) === 'current';
        $uid = $current ? 0 : (int) ( $query['uid'] ?? 0 );

        if( strtolower( $matches[1] ) === 'file' )
        {
            // t3://file?identifier=1:/user_upload/file.pdf references the file by storage and path
            if( $uid <= 0 && is_string( $id = $query['identifier'] ?? null ) && preg_match( '#^(\d+):(/.+)$#', $id, $parts ) ) {
                return $this->identifierUrl( (int) $parts[1], $parts[2] );
            }

            return $uid > 0 ? $this->fileUrl( $uid ) : null;
        }

        $fragment = $query['fragment'] ?? $matches[3] ?? '';
        $lang = $query['_language'] ?? $query['L'] ?? null;
        $lang = is_scalar( $lang ) && is_numeric( $lang ) && (int) $lang >= 0 ? (int) $lang : null;

        return $uid > 0 || $current ? ( $this->page )( $uid, is_string( $fragment ) ? $fragment : '', $lang ) : null;
    }


    /**
     * Imports the TYPO3 file by its sys_file UID and returns its new URL.
     */
    protected function fileUrl( int $uid ): ?string
    {
        if( !$this->file || !( $file = ( $this->file )( $uid ) ) ) {
            return null;
        }

        $this->fileIds[] = $file['id'];
        return $file['url'];
    }


    /**
     * Imports the TYPO3 file by its storage UID and identifier and returns its new URL.
     */
    protected function identifierUrl( int $storage, string $identifier ): ?string
    {
        if( !$this->identifier || !( $file = ( $this->identifier )( $storage, $identifier ) ) ) {
            return null;
        }

        $this->fileIds[] = $file['id'];
        return $file['url'];
    }


    /**
     * Replaces the sources of the images inserted by the TYPO3 rich text editor by their original files.
     *
     * The rich text editor stores the sys_file UID in the "data-htmlarea-file-uid" attribute and uses
     * processed, e.g. resized copies of the images as sources which aren't imported.
     */
    protected function rewriteFileUids( string $html ): string
    {
        if( !$this->file ) {
            return $html;
        }

        return (string) preg_replace_callback( '/<img\b[^>]*\bdata-htmlarea-file-uid\s*=\s*(["\']?)(\d+)\1[^>]*>/is', function( array $matches ): string {
            if( !( $url = $this->fileUrl( (int) $matches[2] ) ) ) {
                return $matches[0];
            }

            $src = 'src="' . htmlspecialchars( $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) . '"';
            $tag = (string) preg_replace( '/\s(?:data-)?srcset\s*=\s*(["\']).*?\1/is', '', $matches[0] );

            return preg_match( '/\ssrc\s*=/i', $tag )
                ? (string) preg_replace( '/\ssrc\s*=\s*(["\']).*?\1|\ssrc\s*=\s*[^\s>]+/is', ' ' . $src, $tag, 1 )
                : (string) preg_replace( '/^<img\b/i', '<img ' . $src, $tag );
        }, $html );
    }
}

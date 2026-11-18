<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;

use Illuminate\Support\Collection;

/**
 * Filters the redirects of the Joomla "Redirect" component like the "System - Redirect" plugin performs them.
 */
class Redirects
{
    /** Lowest HTTP status code which redirects */
    protected const REDIRECT_MIN = 300;
    /** Highest HTTP status code which redirects */
    protected const REDIRECT_MAX = 399;
    /** Option of the "Redirect" component enabling the advanced mode if it's on */
    protected const MODE_OPTION = 'mode';

    /** @var list<string> Plain exclude terms */
    protected array $terms = [];
    /** @var list<string> Regular expressions of the valid exclude terms which are regular expressions */
    protected array $patterns = [];


    /**
     * Initializes the object.
     *
     * Exclude terms which are invalid regular expressions are skipped because they never match like in Joomla.
     *
     * @param array<array-key, mixed> $params Options of the "System - Redirect" plugin
     * @param bool $advanced TRUE if the advanced mode of the "Redirect" component is enabled
     * @param \Closure(string): void|null $warn Shows a warning for the skipped exclude terms or NULL to ignore them
     * @param Origin|null $origin Adds the directory of Joomla sites in subdirectories to the old URLs or NULL if it's unknown
     */
    protected function __construct( array $params, protected bool $advanced, ?\Closure $warn, protected ?Origin $origin = null )
    {
        $excludes = $params['exclude_urls'] ?? null;

        foreach( is_array( $excludes ) ? $excludes : [] as $exclude )
        {
            $term = is_array( $exclude ) ? $exclude['term'] ?? null : null;

            // Joomla skips empty terms including "0"
            if( empty( $term ) || !is_string( $term ) ) {
                continue;
            }

            if( empty( $exclude['regexp'] ) )
            {
                $this->terms[] = $term;
                continue;
            }

            $pattern = '/' . $term . '/i';
            $reason = self::invalidReason( $pattern );

            if( $reason === null ) {
                $this->patterns[] = $pattern;
            } elseif( $warn ) {
                $warn( sprintf( '  Skipped the exclude term "%s" of the redirect plugin because it is an invalid regular expression: %s', $term, $reason ) );
            }
        }
    }


    /**
     * Creates the filter from the settings of the "System - Redirect" plugin and the mode of the "Redirect" component.
     *
     * @param Plugins $plugins Enabled plugins and their options, e.g. the ones of the redirect plugin
     * @param Extensions $extensions Extensions with the options of the "Redirect" component
     * @param \Closure(string): void|null $warn Shows a warning for the skipped exclude terms or NULL to ignore them
     * @param Origin|null $origin Adds the directory of Joomla sites in subdirectories to the old URLs or NULL if it's unknown
     * @return self|null Filter or NULL if the plugin is disabled, so Joomla doesn't redirect at all
     */
    protected static function fromSettings( Plugins $plugins, Extensions $extensions, ?\Closure $warn = null,
        ?Origin $origin = null ): ?self
    {
        if( ( $params = $plugins->redirectParams() ) === null ) {
            return null;
        }

        $component = $extensions->componentParams( Extensions::COMPONENT_REDIRECT );

        // the basic mode performs redirects with all status codes, the advanced mode only the ones with 3xx status codes
        return new self( $params, Value::enabled( $component, self::MODE_OPTION, false ), $warn, $origin );
    }


    /**
     * Returns the published redirects of the "Redirect" component which Joomla performs.
     *
     * Joomla only redirects if the "System - Redirect" plugin is enabled and the old URL isn't excluded by the plugin.
     *
     * @param Reader $reader Reader for the redirects and the options of the "Redirect" component
     * @param Plugins $plugins Enabled plugins and their options, e.g. the ones of the redirect plugin
     * @param \Closure(string): void|null $warn Shows a warning for the skipped exclude terms or NULL to ignore them
     * @param Origin|null $origin Adds the directory of Joomla sites in subdirectories to the old URLs or NULL if it's unknown
     * @return Collection<int, \stdClass>
     */
    public static function all( Reader $reader, Plugins $plugins, ?\Closure $warn = null, ?Origin $origin = null ): Collection
    {
        $redirects = self::fromSettings( $plugins, $reader->extensions(), $warn, $origin );

        return $redirects ? $redirects->filter( $reader->redirects() ) : Collection::make();
    }


    /**
     * Returns the redirects Joomla performs.
     *
     * @param Collection<int, \stdClass> $rows Redirect rows
     * @return Collection<int, \stdClass> Performed redirects
     */
    public function filter( Collection $rows ): Collection
    {
        return $rows->filter( $this->performed( ... ) )->values();
    }


    /**
     * Tests if Joomla performs the redirect.
     *
     * In the advanced mode, Joomla shows an error page instead of redirecting for status codes outside of 300-399.
     */
    protected function performed( \stdClass $row ): bool
    {
        $code = (int) ( $row->header ?? 0 );
        $redirecting = empty( $row->header ) || ( $code >= self::REDIRECT_MIN && $code <= self::REDIRECT_MAX );

        return ( !$this->advanced || $redirecting ) && !$this->excluded( (string) ( $row->old_url ?? '' ) );
    }


    /**
     * Tests if the URL is excluded from redirecting by the "System - Redirect" plugin.
     *
     * Joomla compares the terms with the path, query and fragment of the requested URL, e.g. "/old-page?id=1", which
     * is relative to the host, so it starts with the directory of Joomla sites in subdirectories like "/joomla/old-page".
     * Absolute old URLs contain the directory already, the ones relative to the site get it added.
     */
    protected function excluded( string $url ): bool
    {
        $parts = parse_url( trim( $url ) );

        if( $parts === false ) {
            return false;
        }

        $path = $parts['path'] ?? '';
        $path = !isset( $parts['host'] ) && $this->origin ? $this->origin->requestPath( $path ) : $path;
        $query = isset( $parts['query'] ) ? '?' . $parts['query'] : '';
        $fragment = isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '';
        $url = Uri::decodedPath( $path ) . rawurldecode( $query . $fragment );

        foreach( $this->terms as $term )
        {
            if( str_contains( $url, $term ) ) {
                return true;
            }
        }

        foreach( $this->patterns as $pattern )
        {
            if( preg_match( $pattern, $url ) === 1 ) {
                return true;
            }
        }

        return false;
    }


    /**
     * Returns why the regular expression is invalid without raising a warning.
     *
     * @param string $pattern Regular expression with delimiters
     * @return string|null Error message or NULL if the regular expression is valid
     */
    protected static function invalidReason( string $pattern ): ?string
    {
        $error = null;
        set_error_handler( function( int $no, string $msg ) use ( &$error ) {
            $error = $msg;
            return true;
        } );

        try {
            return preg_match( $pattern, '' ) === false ? $error ?? preg_last_error_msg() : null;
        } finally {
            restore_error_handler();
        }
    }
}

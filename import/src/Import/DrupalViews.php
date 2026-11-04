<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Finds the page displays of the Drupal Views which menu links can refer to.
 */
class DrupalViews
{
    /** @var array<string, array{id: string, display: string, label: string, path: string}>|null Page displays of the Views by URL path */
    protected ?array $pages = null;


    /**
     * Initializes the object.
     *
     * @param DrupalData $data Configuration of the Drupal database
     */
    public function __construct( protected DrupalData $data )
    {
    }


    /**
     * Returns the enabled page displays of the enabled Views with a fixed URL path.
     *
     * Admin pages, pages with arguments in their path like "taxonomy/term/%" and the "node" page
     * of the default front page view are left out.
     *
     * @return array<string, array{id: string, display: string, label: string, path: string}> Views pages by URL path
     */
    public function all(): array
    {
        if( $this->pages !== null ) {
            return $this->pages;
        }

        $pages = [];

        foreach( $this->data->configs() as $name => $config )
        {
            if( !str_starts_with( $name, 'views.view.' ) || !( $config['status'] ?? true ) ) {
                continue;
            }

            foreach( (array) ( $config['display'] ?? [] ) as $display => $settings )
            {
                $options = (array) ( ( (array) $settings )['display_options'] ?? [] );
                $path = trim( (string) ( $options['path'] ?? '' ), '/' );

                if( ( ( (array) $settings )['display_plugin'] ?? '' ) !== 'page' || !( $options['enabled'] ?? true )
                    || $path === '' || $path === 'node' || str_contains( $path, '%' ) || preg_match( '#^admin(/|$)#', $path )
                ) {
                    continue;
                }

                $id = substr( $name, 11 );
                $pages[$path] = ['id' => $id, 'display' => (string) $display, 'label' => (string) ( $config['label'] ?? $id ), 'path' => $path];
            }
        }

        ksort( $pages );

        return $this->pages = $pages;
    }


    /**
     * Returns the Views page the menu link URI refers to.
     *
     * @param string $uri Menu link URI, e.g. "route:view.news.page_1" or "internal:/news"
     * @return array{id: string, display: string, label: string, path: string}|null Views page or NULL if it's no Views page
     */
    public function find( string $uri ): ?array
    {
        if( preg_match( '/^route:view\.([^.;]+)\.([^.;?#]+)/', $uri, $m ) )
        {
            foreach( $this->all() as $view )
            {
                if( $view['id'] === $m[1] && $view['display'] === $m[2] ) {
                    return $view;
                }
            }

            return null;
        }

        return str_starts_with( $uri, 'internal:/' ) ? $this->all()[trim( (string) strtok( substr( $uri, 9 ), '?#' ), '/' )] ?? null : null;
    }
}

<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider as Provider;


/**
 * Base service provider for theme packages.
 *
 * Themes only define their name, which is used for the schema, the view namespace,
 * the admin translation catalog and the published assets in "public/vendor/cms/<name>".
 */
abstract class ThemeProvider extends Provider
{
    /**
     * Theme name matching "[a-z0-9_-]+"
     */
    protected string $name;


    public function boot() : void
    {
        if( !isset( $this->name ) ) {
            throw new \LogicException( sprintf( 'Theme provider "%s" must set the $name property', static::class ) );
        }

        $basedir = dirname( (string) ( new \ReflectionClass( static::class ) )->getFileName(), 2 );

        Schema::register( $basedir, $this->name );
        View::addNamespace( $this->name, $basedir . '/views' );

        if( is_dir( $basedir . '/lang' ) ) {
            $this->loadJsonTranslationsFrom( $basedir . '/lang' );
        }

        if( class_exists( Plugin::class ) ) {
            Plugin::i18n( $this->name );
        }

        $this->publishes( [$basedir . '/public' => public_path( 'vendor/cms/' . $this->name )], 'cms-theme' );
    }
}

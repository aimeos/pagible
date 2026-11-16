<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Prepares the HTML by the content plugins which don't need the custom fields of the page like Joomla.
 *
 * Despite its name, it runs two content plugins: the "Content - Load Modules" plugin replacing the module
 * shortcodes and the "Content - Email Cloaking" plugin linking or unlinking the e-mail addresses. The values
 * of editor and text area fields and the contents of the inserted modules are prepared by both. JoomlaContent
 * uses them for the article texts and category descriptions together with the field shortcodes, so it depends
 * on this class but not the other way round.
 */
class JoomlaModulePreparer
{
    /**
     * Initializes the object.
     *
     * @param JoomlaSite $site Settings of the Joomla site with the enabled plugins and their options
     * @param JoomlaModules $modules Inserts the modules of the module shortcodes
     */
    public function __construct(
        protected JoomlaSite $site,
        protected JoomlaModules $modules
    ) {
    }


    /**
     * Prepares the HTML of an editor or text area field value by the content plugins like Joomla.
     *
     * The shortcodes of the custom fields are kept, the module shortcodes are replaced and the e-mail addresses are cloaked.
     *
     * @param JoomlaRendering $rendering Article or category page showing the field
     * @return string Prepared HTML
     */
    public function value( string $html, JoomlaRendering $rendering ): string
    {
        return $this->cloak( $html, fn( $html ) => $this->replaceModules( $html, $rendering ) );
    }


    /**
     * Replaces the shortcodes of the HTML and cloaks the e-mail addresses afterwards like the "Content - Email Cloaking" plugin.
     *
     * The plugin doesn't cloak the e-mail addresses of texts containing the lower case "{emailcloak=off}" shortcode,
     * which is removed in any case before the other shortcodes are replaced. The modules inserted by the shortcodes
     * remove their own ones when they are prepared by the content plugins.
     *
     * @param \Closure(string): string $replace Replaces the shortcodes of the HTML
     * @return string Prepared HTML
     */
    public function cloak( string $html, \Closure $replace ): string
    {
        $plugins = $this->site->plugins;

        if( !$plugins->emailCloak() ) {
            return $replace( $html );
        }

        if( ( $uncloaked = JoomlaEmails::cloakOff( $html ) ) !== null ) {
            return $replace( $uncloaked );
        }

        // the plugin turns the e-mail addresses into links or shows linked ones as text, depending on its mode
        $html = $replace( $html );
        return $plugins->mailto() ? JoomlaEmails::emails( $html ) : JoomlaEmails::unlinkEmails( $html );
    }


    /**
     * Replaces the module shortcodes by the modules if the "Content - Load Modules" plugin is enabled.
     *
     * @param JoomlaRendering $rendering Article text, category description or field value with its language and menu item
     * @return string HTML with replaced module shortcodes
     */
    public function replaceModules( string $html, JoomlaRendering $rendering ): string
    {
        $plugins = $this->site->plugins;

        if( !$plugins->loadModule() ) {
            return $html;
        }

        $replace = fn( JoomlaShortcode $shortcode ) => $this->modules->shortcode( $shortcode, $rendering, fn( string $html ) => $this->module( $html, $rendering ) );
        return JoomlaShortcodes::modules( $html, $replace, $plugins->style() );
    }


    /**
     * Prepares the HTML of a module inserted by a module shortcode by the content plugins like Joomla.
     *
     * Custom modules with "Prepare Content" enabled are prepared by all content plugins in the "mod_custom.content"
     * context of mod_custom, which has no field values, so field shortcodes in them are removed. This is recursive because modules can contain module shortcodes themselves.
     * The recursion ends because JoomlaModules::shortcode() keeps the shortcodes which are replaced at the moment
     * (see JoomlaModules::$loading), so each nested level needs another shortcode from the module contents.
     *
     * @param JoomlaRendering $rendering Article text or category description containing the module shortcode
     * @return string Prepared HTML
     */
    protected function module( string $html, JoomlaRendering $rendering ): string
    {
        return $this->cloak( $html, function( $html ) use ( $rendering ) {
            if( $this->site->plugins->contentFields() ) {
                $html = JoomlaShortcodes::fields( $html, fn() => '' );
            }

            return $this->replaceModules( $html, $rendering->embedded() );
        } );
    }
}

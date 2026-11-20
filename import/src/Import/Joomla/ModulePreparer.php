<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * Prepares the HTML by the content plugins which don't need the custom fields of the page like Joomla.
 *
 * Despite its name, it runs two content plugins: the "Content - Load Modules" plugin replacing the module
 * shortcodes and the "Content - Email Cloaking" plugin linking or unlinking the e-mail addresses. The values
 * of editor and text area fields and the contents of the inserted modules are prepared by both. Content
 * uses them for the article texts and category descriptions together with the field shortcodes, so it depends
 * on this class but not the other way round.
 */
class ModulePreparer
{
    /** @var array<string, string> Prepared contents of the modules by language, menu item and content hash, see module() */
    protected array $prepared = [];


    /**
     * Initializes the object.
     *
     * @param Site $site Settings of the Joomla site with the enabled plugins and their options
     * @param Modules $modules Inserts the modules of the module shortcodes
     */
    public function __construct(
        protected Site $site,
        protected Modules $modules
    ) {
    }


    /**
     * Prepares the HTML by the enabled content plugins in the order Joomla runs them.
     *
     * The "Content - Fields" plugin replaces the field shortcodes before the "Content - Load Modules" plugin
     * replaces the module shortcodes, so module shortcodes in editor and text area fields are replaced too.
     * The "Content - Page Break" plugin runs afterwards and the "Content - Email Cloaking" plugin last, see cloak().
     *
     * @param Rendering $rendering Article text, category description, field value or module content with its language and menu item
     * @param (\Closure(Shortcode): ?string)|null $fields Replaces the field shortcodes, NULL keeps them
     * @param bool $pageBreaks TRUE to turn the page breaks into headings
     * @return string Prepared HTML
     */
    public function prepare( string $html, Rendering $rendering, ?\Closure $fields = null, bool $pageBreaks = false ): string
    {
        return $this->cloak( $html, function( $html ) use ( $rendering, $fields, $pageBreaks ) {
            $plugins = $this->site->plugins;

            if( $fields && $plugins->contentFields() ) {
                $html = Shortcodes::fields( $html, $fields );
            }

            $html = $this->replaceModules( $html, $rendering );

            if( $pageBreaks && $plugins->pageBreak() ) {
                $html = PageBreaks::headings( $html, $this->site->languages->locale( $rendering->language ) );
            }

            return $html;
        } );
    }


    /**
     * Prepares the HTML of an editor or text area field value by the content plugins like Joomla.
     *
     * The shortcodes of the custom fields are kept, the module shortcodes are replaced and the e-mail addresses are cloaked.
     *
     * @param Rendering $rendering Article or category page showing the field
     * @return string Prepared HTML
     */
    public function value( string $html, Rendering $rendering ): string
    {
        return $this->prepare( $html, $rendering );
    }


    /**
     * Replaces the shortcodes of the HTML and cloaks the e-mail addresses afterwards like the "Content - Email Cloaking" plugin.
     *
     * The plugin doesn't cloak the e-mail addresses of texts containing the lower case "{emailcloak=off}" shortcode,
     * which is removed in any case before the other shortcodes are replaced. The modules inserted by the shortcodes
     * remove their own ones when they are prepared by the content plugins.
     *
     * Each nested module cloaks its own e-mail addresses before they are cloaked again as part of the text around
     * them. This is kept because the results differ if the text disables the plugin or the module is in a link.
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

        if( ( $uncloaked = Emails::cloakOff( $html ) ) !== null ) {
            return $replace( $uncloaked );
        }

        // the plugin turns the e-mail addresses into links or shows linked ones as text, depending on its mode
        $html = $replace( $html );
        return $plugins->mailto() ? Emails::emails( $html ) : Emails::unlinkEmails( $html );
    }


    /**
     * Replaces the module shortcodes by the modules if the "Content - Load Modules" plugin is enabled.
     *
     * @param Rendering $rendering Article text, category description or field value with its language and menu item
     * @return string HTML with replaced module shortcodes
     */
    public function replaceModules( string $html, Rendering $rendering ): string
    {
        $plugins = $this->site->plugins;

        if( !$plugins->loadModule() ) {
            return $html;
        }

        $replace = fn( Shortcode $shortcode ) => $this->modules->shortcode( $shortcode, $rendering, fn( string $html ) => $this->module( $html, $rendering ) );
        return Shortcodes::modules( $html, $replace, $plugins->style() );
    }


    /**
     * Prepares the HTML of a module inserted by a module shortcode by the content plugins like Joomla.
     *
     * Custom modules with "Prepare Content" enabled are prepared by all content plugins in the "mod_custom.content"
     * context of mod_custom, which has no field values, so field shortcodes in them are removed. This is recursive because modules can contain module shortcodes themselves.
     * The recursion ends because Modules::shortcode() keeps the shortcodes which are replaced at the moment
     * (see Modules::$loading), so each nested level needs another shortcode from the module contents.
     *
     * The prepared contents don't depend on the article, only on the language and the menu item, so they are reused
     * unless a shortcode was kept while preparing them, because then they depend on the shortcodes replaced around them.
     *
     * @param Rendering $rendering Article text or category description containing the module shortcode
     * @return string Prepared HTML
     */
    protected function module( string $html, Rendering $rendering ): string
    {
        $key = $rendering->language . '/' . $rendering->itemId . '/' . hash( 'xxh128', $html );

        if( isset( $this->prepared[$key] ) ) {
            return $this->prepared[$key];
        }

        $kept = $this->modules->kept();
        $result = $this->prepare( $html, $rendering->embedded(), fn() => '' );

        if( $this->modules->kept() === $kept ) {
            $this->prepared[$key] = $result;
        }

        return $result;
    }
}

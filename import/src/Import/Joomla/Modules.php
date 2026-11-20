<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;

use Illuminate\Support\Collection;


/**
 * Inserts the Joomla modules of the shortcodes of the "Content - Load Modules" plugin.
 *
 * Custom modules are inserted by their content, other modules aren't supported and removed.
 *
 * @phpstan-import-type Args from Shortcodes
 */
class Modules
{
    use Remembers;

    /** Prefix of the module types, which can be omitted in the shortcodes */
    protected const PREFIX = 'mod_';
    /** Type of the custom modules, the only supported ones */
    protected const CUSTOM = 'mod_custom';
    /** HTML tag of the module titles if the module doesn't configure one */
    protected const HEADER_TAG = 'h3';
    /** HTML tags allowed for the module titles, other configured values use the default tag */
    protected const HEADER_TAGS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span'];
    /** Option of the modules for replacing the shortcodes in their content */
    protected const OPTION_PREPARE = 'prepare_content';
    /** Option of the modules for their module style */
    protected const OPTION_STYLE = 'style';
    /** Option of the modules for the HTML tag of their title */
    protected const OPTION_HEADER = 'header_tag';
    /** Module style which uses the one of the shortcode */
    protected const SHORTCODE_STYLE = '0';
    /** Joomla shows the module titles if the modules don't configure it */
    protected const SHOW_TITLE = 1;
    /** Menu assignment of modules shown on all pages, negative menu item IDs exclude the pages of these menu items */
    protected const ALL_PAGES = 0;
    /** Menu item ID of articles and categories Joomla doesn't show in a menu item */
    protected const NO_ITEM = 0;
    /** Warning for modules which aren't custom modules with the module and the shortcode */
    protected const UNSUPPORTED = '  Removed module %s of shortcode %s because only custom modules are supported';

    /** @var Collection<int, \stdClass>|null Published modules of the site guests can see, fetched on first use */
    protected ?Collection $modules = null;
    /** @var array<string, Collection<int, \stdClass>>|null Published modules guests can see by lower case position, built on first use */
    protected ?array $positions = null;
    /** @var array<string, mixed> Values fetched once by cache key like the menu assignments, NULL ones are cached too */
    protected array $cache = [];
    /** @var array<string, bool> Texts of the module shortcodes replaced at the moment as keys, so modules loading themselves aren't inserted again */
    protected array $loading = [];
    /** Number of shortcodes kept because their modules were loading at the moment, see kept() */
    protected int $kept = 0;


    /**
     * Initializes the object.
     *
     * @param Site $site Settings of the Joomla site with the access checks of the modules
     * @param Reader $reader Reader for the modules and their menu assignments
     * @param \Closure(string): void $warnOnce Shows a warning only once, so the removed modules of shortcodes in several texts are reported once
     */
    public function __construct(
        protected Site $site,
        protected Reader $reader,
        protected \Closure $warnOnce
    ) {
    }


    /**
     * Returns the output of a module shortcode.
     *
     * Custom modules are inserted by their content whose shortcodes are replaced too, other modules aren't supported and removed.
     *
     * @param Shortcode $shortcode Module shortcode like "{loadposition banner}", "{loadmoduleid 5}" or "{loadmodule custom}"
     * @param Rendering $rendering Article text or category description with its language and menu item
     * @param \Closure(string): string $prepare Replaces the shortcodes of the content plugins in the content of the modules
     * @return string|null HTML replacing the shortcode or NULL to keep the shortcode
     */
    public function shortcode( Shortcode $shortcode, Rendering $rendering, \Closure $prepare ): ?string
    {
        $key = $shortcode->text();

        // like Joomla, shortcodes in modules loading themselves are kept to avoid endless loops
        if( isset( $this->loading[$key] ) )
        {
            $this->kept++;
            return null;
        }

        $modules = $this->select( $shortcode->name, $shortcode->args, $rendering->language, $rendering->itemId );
        $style = $shortcode->args['style'] ?? Shortcodes::NO_STYLE;
        $html = '';

        $this->loading[$key] = true;

        try
        {
            foreach( $modules as $module )
            {
                if( $this->type( $module ) !== self::CUSTOM )
                {
                    ( $this->warnOnce )( sprintf( self::UNSUPPORTED, Row::label( $module ), $key ) );
                    continue;
                }

                $html .= $this->module( $module, $style, $prepare );
            }
        }
        finally
        {
            unset( $this->loading[$key] );
        }

        return $html;
    }


    /**
     * Returns the number of shortcodes kept so far because their modules were loading at the moment.
     *
     * Modules prepared without keeping a shortcode don't depend on the shortcodes replaced around them,
     * so their prepared contents can be reused, see ModulePreparer::module().
     */
    public function kept(): int
    {
        return $this->kept;
    }


    /**
     * Returns the published modules of the site guests can see.
     *
     * @return Collection<int, \stdClass> Modules ordered by their position
     */
    protected function modules(): Collection
    {
        $public = fn( $module ) => $this->site->access->restriction( $module ) === null;
        return $this->modules ??= $this->reader->modules()->filter( $public )->values();
    }


    /**
     * Returns the menu items of the modules.
     *
     * @return array<int|string, list<int>>|null Menu item IDs by module ID, 0 for all and negative IDs for the excluded menu items,
     *  or NULL if the assignments are unknown
     */
    protected function menus(): ?array
    {
        // NULL is a valid result, which is cached too
        return $this->remember( $this->cache, 'menus', fn() => $this->reader->moduleMenus() );
    }


    /**
     * Returns the published modules of the site guests can see by their position.
     *
     * Joomla compares the positions case-insensitively, so they are indexed in lower case once.
     *
     * @param string $position Module position like "banner"
     * @return Collection<int, \stdClass> Modules of the position ordered like by modules()
     */
    protected function positioned( string $position ): Collection
    {
        $this->positions ??= $this->modules()->groupBy( fn( $module ) => strtolower( Value::string( $module->position ?? null ) ) )->all();
        return $this->positions[strtolower( $position )] ?? new Collection();
    }


    /**
     * Returns the module type, e.g. "mod_custom".
     */
    protected function type( \stdClass $module ): string
    {
        return Value::string( $module->module ?? null );
    }


    /**
     * Returns the HTML of a custom module like Joomla renders it in the module style.
     *
     * @param string $style Module style of the shortcode, overwritten by the style of the module
     * @param \Closure(string): string $prepare Replaces the shortcodes of the content plugins in the HTML
     */
    protected function module( \stdClass $module, string $style, \Closure $prepare ): string
    {
        $params = Row::params( $module );
        $content = Value::text( $module->content ?? null );

        // custom modules prepare their content by the content plugins by default, custom fields of the article aren't available there
        if( Value::enabled( $params, self::OPTION_PREPARE ) ) {
            $content = $prepare( $content );
        }

        if( $content !== '' && $this->showsTitle( $module, $this->style( $params, $style ) ) )
        {
            $header = $this->headerTag( $params );
            $content = '<' . $header . '>' . e( Row::title( $module ) ) . '</' . $header . '>' . $content;
        }

        return $content;
    }


    /**
     * Returns the module style Joomla renders the module in.
     *
     * @param array<array-key, mixed> $params Options of the module
     * @param string $style Module style of the shortcode, used if the module doesn't configure an own one
     * @return string Module style without template name, e.g. "html5" or "card"
     */
    protected function style( array $params, string $style ): string
    {
        // styles of modules usually contain the template name, e.g. "System-html5" or "cassiopeia-card"
        $custom = $params[self::OPTION_STYLE] ?? null;

        // Joomla removes the template name only if there's one and keeps styles without it like "html5"
        if( is_string( $custom ) && $custom !== '' && $custom !== self::SHORTCODE_STYLE ) {
            return explode( '-', $custom, 2 )[1] ?? $custom;
        }

        return $style;
    }


    /**
     * Tests if Joomla shows the title of the module before its content.
     *
     * All module styles except "none" show the title unless the module hides it.
     *
     * @param string $style Module style from style(), may contain several styles separated by spaces
     */
    protected function showsTitle( \stdClass $module, string $style ): bool
    {
        $styled = array_diff( explode( ' ', $style ), [Shortcodes::NO_STYLE, ''] ) !== [];
        return !empty( $module->showtitle ?? self::SHOW_TITLE ) && $styled;
    }


    /**
     * Returns the HTML tag of the module title.
     *
     * @param array<array-key, mixed> $params Options of the module
     * @return string Configured HTML tag in lower case if it's allowed, otherwise "h3"
     */
    protected function headerTag( array $params ): string
    {
        $header = $params[self::OPTION_HEADER] ?? null;
        $header = is_string( $header ) ? strtolower( trim( $header ) ) : '';

        return in_array( $header, self::HEADER_TAGS, true ) ? $header : self::HEADER_TAG;
    }


    /**
     * Returns the modules the shortcode inserts.
     *
     * @param string $name Shortcode name, i.e. Shortcodes::LOAD_POSITION, Shortcodes::LOAD_MODULE or Shortcodes::LOAD_MODULE_ID
     * @param Args $args Module position, module type and title or module ID and the module style by name
     * @param string $language Joomla language tag of the article or category
     * @param int $itemId ID of the menu item Joomla shows the article or category in, 0 for none
     * @return Collection<int, \stdClass> Modules shown on the page
     */
    protected function select( string $name, array $args, string $language, int $itemId ): Collection
    {
        $title = $args['title'] ?? '';
        $titled = fn( $module ) => $title === '' || Row::title( $module ) === $title;

        // the modules of a position are indexed, the others are tested by the cheap tests of the shortcode
        // before the ones of the language and menu assignment, the remaining shortcode is LOAD_MODULE
        [$candidates, $selected] = match( $name ) {
            Shortcodes::LOAD_POSITION => [$this->positioned( $args['position'] ?? '' ), fn( $module ) => true],
            Shortcodes::LOAD_MODULE_ID => [$this->modules(), fn( $module ) => Value::string( $module->id ?? null ) === ( $args['id'] ?? '' )],
            default => [$this->modules(), fn( $module ) => $this->typed( $module, $args['type'] ?? '' ) && $titled( $module )],
        };
        $shown = fn( $module ) => $selected( $module ) && $this->site->shows( Row::language( $module ), $language ) && $this->assigned( $module, $itemId );
        $modules = $candidates->filter( $shown );

        return $name === Shortcodes::LOAD_MODULE ? $modules->take( 1 ) : $modules;
    }


    /**
     * Tests if the module has the module type, which can be given with or without the "mod_" prefix.
     *
     * The type is compared case-sensitively like by Joomla's ModuleHelper::getModule(), which compares the loaded
     * modules in PHP, so e.g. "MOD_CUSTOM" or "Custom" don't match "mod_custom".
     *
     * @param string $type Module type like "mod_custom" or "custom"
     */
    protected function typed( \stdClass $module, string $type ): bool
    {
        $name = $this->type( $module );

        $short = str_starts_with( $name, self::PREFIX ) ? substr( $name, strlen( self::PREFIX ) ) : $name;

        return in_array( $type, [$name, $short], true );
    }


    /**
     * Tests if Joomla shows the module on the pages of the menu item.
     *
     * Modules are shown on the pages of their menu items, on all pages or on all pages except the excluded ones,
     * modules without menu items aren't shown at all.
     *
     * @param int $itemId ID of the menu item Joomla shows the article or category in, 0 for none
     */
    protected function assigned( \stdClass $module, int $itemId ): bool
    {
        // without menu assignments in the database, all modules are shown
        if( ( $assignments = $this->menus() ) === null ) {
            return true;
        }

        $menus = $assignments[$module->id] ?? [];

        // negative IDs exclude the pages of the menu items from all pages
        if( $itemId !== self::NO_ITEM && in_array( -$itemId, $menus, true ) ) {
            return false;
        }

        // shown on the pages of the menu item, on all pages or on all pages except the excluded ones
        foreach( $menus as $id )
        {
            if( $id === $itemId || $id <= self::ALL_PAGES ) {
                return true;
            }
        }

        return false;
    }
}

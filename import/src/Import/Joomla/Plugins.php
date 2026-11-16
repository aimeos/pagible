<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * Returns which Joomla plugins are enabled and the options of the plugins the import uses.
 *
 * The plugins are fetched on first use and cached by the extensions reader, so only the used ones are fetched.
 * Like Joomla, plugins whose access level guests can't see aren't loaded.
 *
 * If the installed extensions are unknown, e.g. without extensions table, all plugins count as disabled.
 * The only exception are the item associations, which are used then, see itemAssociations().
 */
class Plugins
{
    /** Joomla plugin groups of the content, system and custom field plugins */
    protected const GROUP_CONTENT = 'content';
    protected const GROUP_SYSTEM = 'system';
    protected const GROUP_FIELDS = 'fields';

    /** Name of the "Content - Fields" and "System - Fields" plugins */
    protected const FIELDS = 'fields';
    /** Name of the "Content - Load Modules" plugin */
    protected const LOAD_MODULE = 'loadmodule';
    /** Name of the "Content - Page Break" plugin */
    protected const PAGE_BREAK = 'pagebreak';
    /** Name of the "Content - Email Cloaking" plugin */
    protected const EMAIL_CLOAK = 'emailcloak';
    /** Name of the "System - SEF" plugin */
    protected const SEF = 'sef';
    /** Name of the "System - Redirect" plugin */
    protected const REDIRECT = 'redirect';
    /** Name of the "System - Language Filter" plugin which shows the articles only on the site of their language */
    protected const LANGUAGE_FILTER = 'languagefilter';

    /** Option of the "Content - Email Cloaking" plugin for the mode of the cloaked e-mail addresses */
    protected const CLOAK_MODE = 'mode';
    /** Mode of the "Content - Email Cloaking" plugin showing the e-mail addresses as text instead of links */
    protected const CLOAK_AS_TEXT = '0';
    /** Option of the "Content - Load Modules" plugin for the default module style */
    protected const MODULE_STYLE = 'style';
    /** Option of the "System - SEF" plugin for strict routing */
    protected const STRICT_ROUTING = 'strictrouting';
    /** Option of the "System - Language Filter" plugin for using the associations as translations */
    protected const ITEM_ASSOCIATIONS = 'item_associations';


    /**
     * Initializes the object.
     *
     * @param Extensions $extensions Reads the plugins from the Joomla database
     * @param Levels $levels View levels for checking if guests can see the plugins
     */
    public function __construct( protected Extensions $extensions, protected Levels $levels )
    {
    }


    /**
     * Tests if the "Content - Fields" plugin replaces the field shortcodes.
     */
    public function contentFields(): bool
    {
        return $this->enabled( self::GROUP_CONTENT, self::FIELDS );
    }


    /**
     * Tests if the "System - Fields" plugin shows the custom fields around the text.
     */
    public function systemFields(): bool
    {
        return $this->enabled( self::GROUP_SYSTEM, self::FIELDS );
    }


    /**
     * Returns the options of the plugin rendering the custom fields of the type.
     *
     * @param string $type Field type like "text" or "list"
     * @return array<array-key, mixed>|null Options or NULL if the plugin isn't enabled, so Joomla doesn't show the fields
     */
    public function fieldParams( string $type ): ?array
    {
        return $this->params( self::GROUP_FIELDS, $type );
    }


    /**
     * Tests if Joomla uses the associations of the articles, categories and menu items as their translations.
     *
     * Like Associations::isEnabled(), they are only used if the "System - Language Filter" plugin is enabled and
     * its "Item Associations" option isn't disabled. If the installed extensions are unknown, the associations are used.
     */
    public function itemAssociations(): bool
    {
        // unlike the other plugin checks, the associations are used if the installed extensions are unknown
        if( !$this->extensions->known() ) {
            return true;
        }

        $params = $this->params( self::GROUP_SYSTEM, self::LANGUAGE_FILTER );
        return $params !== null && Value::enabled( $params, self::ITEM_ASSOCIATIONS );
    }


    /**
     * Tests if the "System - Language Filter" plugin is enabled, so Joomla shows articles only on the site of their language.
     */
    public function languageFilter(): bool
    {
        return $this->enabled( self::GROUP_SYSTEM, self::LANGUAGE_FILTER );
    }


    /**
     * Tests if the "Content - Load Modules" plugin replaces the module shortcodes.
     */
    public function loadModule(): bool
    {
        return $this->enabled( self::GROUP_CONTENT, self::LOAD_MODULE );
    }


    /**
     * Returns the default module style of the "Content - Load Modules" plugin.
     *
     * All module styles except "none" are treated alike, e.g. "html5" or "xhtml" used by Joomla 3.
     */
    public function style(): string
    {
        $style = $this->params( self::GROUP_CONTENT, self::LOAD_MODULE )[self::MODULE_STYLE] ?? null;
        return is_string( $style ) ? $style : Shortcodes::NO_STYLE;
    }


    /**
     * Tests if the "Content - Page Break" plugin replaces the page breaks.
     */
    public function pageBreak(): bool
    {
        return $this->enabled( self::GROUP_CONTENT, self::PAGE_BREAK );
    }


    /**
     * Tests if the "Content - Email Cloaking" plugin replaces the e-mail addresses.
     */
    public function emailCloak(): bool
    {
        return $this->enabled( self::GROUP_CONTENT, self::EMAIL_CLOAK );
    }


    /**
     * Tests if the "Content - Email Cloaking" plugin links the e-mail addresses, otherwise it shows them as text.
     */
    public function mailto(): bool
    {
        $params = $this->params( self::GROUP_CONTENT, self::EMAIL_CLOAK );
        return $params !== null && Value::string( $params[self::CLOAK_MODE] ?? null ) !== self::CLOAK_AS_TEXT;
    }


    /**
     * Tests if the "System - SEF" plugin makes the URLs absolute.
     */
    public function sef(): bool
    {
        return $this->enabled( self::GROUP_SYSTEM, self::SEF );
    }


    /**
     * Tests if the strict routing of the "System - SEF" plugin in Joomla 5.2+ is on.
     */
    public function strictRouting(): bool
    {
        $params = $this->params( self::GROUP_SYSTEM, self::SEF );
        return $params !== null && Value::enabled( $params, self::STRICT_ROUTING, false );
    }


    /**
     * Returns the options of the "System - Redirect" plugin.
     *
     * @return array<array-key, mixed>|null Options or NULL if the plugin isn't enabled
     */
    public function redirectParams(): ?array
    {
        return $this->params( self::GROUP_SYSTEM, self::REDIRECT );
    }


    /**
     * Tests if the plugin is enabled and guests can see it.
     *
     * @param string $group Plugin group, e.g. "system"
     * @param string $name Plugin name, e.g. "sef"
     */
    protected function enabled( string $group, string $name ): bool
    {
        return $this->params( $group, $name ) !== null;
    }


    /**
     * Returns the options of the plugin if it's enabled and guests can see it.
     *
     * @param string $group Plugin group, e.g. "system"
     * @param string $name Plugin name, e.g. "sef"
     * @return array<array-key, mixed>|null Options or NULL if the plugin isn't enabled
     */
    protected function params( string $group, string $name ): ?array
    {
        $plugin = $this->extensions->plugin( $group, $name );

        if( $plugin === null || !$this->levels->isPublic( $plugin->access ) ) {
            return null;
        }

        return $plugin->params;
    }
}

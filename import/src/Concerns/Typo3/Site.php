<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Typo3;


/**
 * Maps the TYPO3 page trees to their root pages, domains and languages.
 */
trait Site
{
    /** @var array<int, int|null> UIDs of the root pages of the site configurations by TYPO3 page UID */
    protected array $siteRoots = [];


    /**
     * Tests if the TYPO3 page is imported as root page of a domain.
     *
     * Top-level pages are root pages and site roots inside the page tree if they have their own domain,
     * which includes the root pages of site configurations even if TYPO3 doesn't flag them as site root.
     */
    protected function isRoot( object $t3Page ): bool
    {
        return $this->tree->pid( $t3Page ) === 0
            || $this->site->has( $t3Page ) && ( !empty( $t3Page->is_siteroot ) || $this->site->configured( (int) ( $t3Page->uid ?? 0 ) ) );
    }


    /**
     * Returns the UID of the root page of the site configuration the TYPO3 page or page translation belongs to.
     *
     * @return int|null Root page UID or NULL if the page isn't part of a configured site
     */
    protected function siteRoot( object $t3Page ): ?int
    {
        $uid = (int) ( ( $t3Page->l10n_parent ?? 0 ) ?: ( $t3Page->uid ?? 0 ) );

        if( array_key_exists( $uid, $this->siteRoots ) ) {
            return $this->siteRoots[$uid];
        }

        $root = null;

        for( $next = $uid, $depth = 0; $next > 0 && $depth < 100; $depth++ )
        {
            if( $this->site->configured( $next ) )
            {
                $root = $next;
                break;
            }

            $next = (int) ( $this->pagesById->get( $next )->pid ?? 0 );
        }

        return $this->siteRoots[$uid] = $root;
    }


    /**
     * Returns the language of the TYPO3 page in default language.
     *
     * The --lang option applies to all pages if it's given, otherwise the default language of the
     * site configuration of the page is used and the default value of --lang for pages without one.
     */
    protected function langOf( object $t3Page ): string
    {
        if( $this->explicitLang() ) {
            return $this->lang;
        }

        return $this->site->defaultLang( $this->siteRoot( $t3Page ) ) ?: $this->lang;
    }


    /**
     * Tests if the --lang option is given.
     */
    protected function explicitLang(): bool
    {
        return $this->input->hasParameterOption( '--lang' );
    }


    /**
     * Returns the domain of the TYPO3 page from its next root page.
     */
    protected function domainOf( object $t3Page ): string
    {
        $page = $this->tree->root( $t3Page );

        // site roots with own domain inside the page tree start a new domain
        for( $next = $t3Page, $depth = 0; $next && $depth < 100; $next = $this->tree->parent( $next ), $depth++ )
        {
            if( $this->isRoot( $next ) )
            {
                $page = $next;
                break;
            }
        }

        return $this->site->domain( $page );
    }


    /**
     * Returns the domains of the TYPO3 root page and the translations with own domain and their languages.
     *
     * @return array<string, string> Language codes by domain, the domain of the root page first
     */
    protected function hostsOf( object $t3Root ): array
    {
        $lang = $this->langOf( $t3Root );
        $hosts = [$this->site->domain( $t3Root ) => $lang];
        $siteRoot = $this->siteRoot( $t3Root );

        foreach( $this->languages->imported( $this->explicitLang() || !$this->site->sites() ? $this->lang : '' ) as $uid )
        {
            $language = $this->languages->get( $uid, $siteRoot );

            if( $language && $language['domain'] !== '' && $language['lang'] !== $lang ) {
                $hosts[$language['domain']] ??= $language['lang'];
            }
        }

        return $hosts;
    }
}

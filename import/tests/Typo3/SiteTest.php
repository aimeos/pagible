<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests\Typo3;

use Aimeos\Cms\Import\Pages;
use Aimeos\Cms\Import\Typo3;
use Aimeos\Cms\Models\PageView;
use Illuminate\Support\Collection;


/**
 * Tests the TYPO3 sites, languages, page tree, URLs and destination pages.
 */
class SiteTest extends TestAbstract
{
    public function testSiteReadsLanguagesPerSite(): void
    {
        if( !class_exists( \Symfony\Component\Yaml\Yaml::class ) ) {
            $this->markTestSkipped( 'symfony/yaml is not installed' );
        }

        $path = sys_get_temp_dir() . '/t3sites-' . uniqid();
        $files = [
            'main' => "rootPageId: 1\nbase: '%env(BASE)%'\nbaseVariants:\n  - base: 'https://dev.example/'\n    condition: 'applicationContext == \"Development\"'\n"
                . "  - base: 'https://www.example.com/'\n    condition: 'applicationContext == \"Production\"'\nlanguages:\n"
                . "  - languageId: 0\n    locale: en_GB.UTF-8\n  - languageId: 1\n    locale: de_DE.UTF-8\n    fallbackType: fallback\n    fallbacks: '2,0'\n"
                . "  - languageId: 2\n    locale: de_AT.UTF-8\n    base: 'https://example.at/'\n  - languageId: 3\n    locale: it_IT\n    enabled: false\n",
            'other' => "rootPageId: 10\nbase: 'https://other.example/'\nlanguages:\n  - languageId: 0\n    iso-639-1: fr\n  - languageId: 1\n    iso-639-1: es\n    fallbackType: free\n",
        ];

        foreach( $files as $name => $content )
        {
            mkdir( $path . '/config/sites/' . $name, 0777, true );
            file_put_contents( $path . '/config/sites/' . $name . '/config.yaml', $content );
        }

        try
        {
            $site = new Typo3\Site( [], fn() => [] );
            $this->assertSame( 2, $site->configure( $path ) );
        }
        finally
        {
            foreach( array_keys( $files ) as $name )
            {
                unlink( $path . '/config/sites/' . $name . '/config.yaml' );
                rmdir( $path . '/config/sites/' . $name );
            }

            rmdir( $path . '/config/sites' );
            rmdir( $path . '/config' );
            rmdir( $path );
        }

        // the base variant of the production context is used if the base can't be resolved
        $this->assertSame( 'www.example.com', $site->domain( (object) ['uid' => 1] ) );
        $this->assertSame( ['en', 'fr', ''], [$site->defaultLang( 1 ), $site->defaultLang( 10 ), $site->defaultLang( null )] );
        $this->assertTrue( $site->configured( 10 ) );
        $this->assertFalse( $site->configured( 2 ) );

        // the language UIDs are per site and disabled languages are skipped
        $this->assertSame( [1 => ['lang' => 'de', 'domain' => ''], 2 => ['lang' => 'de', 'domain' => 'example.at']], $site->languages( 1 ) );
        $this->assertSame( [1 => ['lang' => 'es', 'domain' => '']], $site->languages( 10 ) );
        $this->assertSame( [1 => 'de', 2 => 'de'], array_map( fn( $l ) => $l['lang'], $site->languages() ) );
        $this->assertSame( ['fallback', 'free', 'fallback'], [$site->fallback( 1, 1 ), $site->fallback( 1, 10 ), $site->fallback( 1 )] );
        // the default language isn't part of the fallback chain
        $this->assertSame( [[2], [], [2]], [$site->chain( 1, 1 ), $site->chain( 1, 10 ), $site->chain( 1 )] );

        $languages = new Typo3\Languages( [1 => ['lang' => 'nl', 'domain' => '']], [], $site->sites() );
        $this->assertSame( ['lang' => 'es', 'domain' => ''], $languages->get( 1, 10 ) );
        $this->assertSame( ['lang' => 'de', 'domain' => ''], $languages->get( 1, 1 ) );
        // pages without site configuration use the sys_language records
        $this->assertSame( ['lang' => 'nl', 'domain' => ''], $languages->get( 1 ) );
        $this->assertNull( $languages->get( 3, 1 ) );
        $this->assertSame( [1, 2], $languages->imported( '' ) );

        // the --language options apply to all sites
        $this->assertSame( ['lang' => 'pt', 'domain' => ''], ( new Typo3\Languages( [], ['1:pt'], $site->sites() ) )->get( 1, 10 ) );
    }


    public function testSiteUsesRegionalCodesOnTheSameDomain(): void
    {
        if( !class_exists( \Symfony\Component\Yaml\Yaml::class ) ) {
            $this->markTestSkipped( 'symfony/yaml is not installed' );
        }

        $path = sys_get_temp_dir() . '/t3sites-' . uniqid();
        $content = "rootPageId: 1\nbase: 'https://example.com/'\nlanguages:\n"
            . "  - languageId: 0\n    locale: de_DE.UTF-8\n  - languageId: 1\n    locale: de_CH.UTF-8\n    base: /ch/\n"
            . "  - languageId: 2\n    locale: fr_FR.UTF-8\n    base: /fr/\n  - languageId: 3\n    locale: fr_CH.UTF-8\n    hreflang: fr-ch\n    base: /fr-ch/\n"
            . "  - languageId: 4\n    locale: de_AT.UTF-8\n    base: 'https://example.com/at/'\n";

        mkdir( $path . '/config/sites/main', 0777, true );
        file_put_contents( $path . '/config/sites/main/config.yaml', $content );

        try
        {
            $site = new Typo3\Site( [], fn() => [] );
            $site->configure( $path );
        }
        finally
        {
            unlink( $path . '/config/sites/main/config.yaml' );
            rmdir( $path . '/config/sites/main' );
            rmdir( $path . '/config/sites' );
            rmdir( $path . '/config' );
            rmdir( $path );
        }

        // languages with the same code on the same domain would be one language in Pagible otherwise
        $this->assertSame( ['de-CH', 'fr', 'fr-CH', 'de-AT'], array_column( $site->languages( 1 ), 'lang' ) );
        $this->assertSame( ['', '', '', ''], array_column( $site->languages( 1 ), 'domain' ) );
    }


    public function testUrlsUseTheFallbackChainOfTranslations(): void
    {
        $urls = new Typo3\Urls( Collection::make( [2 => (object) ['uid' => 2, 'pid' => 1, 'slug' => '/about']] ), [2 => [2 => '/ueber-uns']] );

        $this->assertSame( '/ueber-uns', $urls->page( 2, '', ['uid' => 1, 'source' => '', 'chain' => [2]] ) );
        $this->assertSame( '/about', $urls->page( 2, '', ['uid' => 1, 'source' => ''] ) );
        $this->assertSame( 'https://example.com/about#top', $urls->page( 2, 'top', ['uid' => 3, 'source' => 'example.com', 'chain' => [4]] ) );
    }


    public function testUrlsResolvesPagesAndRedirects(): void
    {
        $page = (object) ['uid' => 1, 'doktype' => 1, 'slug' => '/about/'];
        $shortcut = (object) ['uid' => 2, 'doktype' => 4, 'shortcut' => 3, 'shortcut_mode' => 0];
        $external = (object) ['uid' => 4, 'doktype' => 3, 'url' => ' https://x.org '];
        $pages = $this->pages( [
            1 => $page,
            2 => $shortcut,
            3 => (object) ['uid' => 3, 'doktype' => 4, 'shortcut' => 1],
            4 => $external,
            5 => (object) ['uid' => 5, 'doktype' => 4, 'shortcut' => 1, 'shortcut_mode' => 1],
            6 => (object) ['uid' => 6, 'doktype' => 4, 'shortcut' => 7],
            7 => (object) ['uid' => 7, 'doktype' => 4, 'shortcut' => 6],
        ] );
        $urls = new Typo3\Urls( $pages, [1 => [1 => '/ueber']] );

        $this->assertSame( '/about', $urls->page( 1 ) );
        $this->assertSame( '/about#top', $urls->page( 1, '#top' ) );
        $this->assertSame( '/about#top', $urls->page( 1, 'top' ) );
        $this->assertSame( '/about', $urls->page( 2 ) );
        $this->assertSame( 'https://x.org', $urls->page( 4 ) );
        $this->assertNull( $urls->page( 5 ) );
        $this->assertNull( $urls->page( 6 ) );
        $this->assertNull( $urls->page( 99 ) );

        $this->assertSame( '/ueber', $urls->page( 1, '', ['uid' => 1, 'source' => 'example.com'] ) );
        $this->assertSame( '/ueber', $urls->page( 2, '', ['uid' => 1, 'source' => ''] ) );
        $this->assertSame( 'https://example.com/about', $urls->page( 1, '', ['uid' => 2, 'source' => 'example.com'] ) );
        $this->assertSame( '/about', $urls->page( 1, '', ['uid' => 2, 'source' => ''] ) );

        $this->assertSame( '', $urls->redirect( $page ) );
        $this->assertSame( '/about', $urls->redirect( $shortcut ) );
        $this->assertSame( 'https://x.org', $urls->redirect( $external ) );
        $this->assertSame( 'a/b', Typo3\Urls::slug( '/a/b/' ) );
        $this->assertSame( '', Typo3\Urls::slug( null ) );
    }


    public function testUrlsReplaceContentAnchorsAndHonorLanguageConfig(): void
    {
        $anchor = Typo3\Urls::anchor( 12 );

        // the anchors are valid element IDs which are the same for each import
        $this->assertMatchesRegularExpression( '/^[A-Za-z][A-Za-z0-9_-]{5}$/', $anchor );
        $this->assertSame( $anchor, Typo3\Urls::anchor( 12 ) );
        $this->assertNotSame( $anchor, Typo3\Urls::anchor( 13 ) );
        $this->assertSame( $anchor, Typo3\Urls::fragment( '#c12' ) );
        $this->assertSame( 'top', Typo3\Urls::fragment( 'top' ) );
        $this->assertSame( 'c12x', Typo3\Urls::fragment( 'c12x' ) );

        $urls = new Typo3\Urls( $this->pages( [
            1 => (object) ['uid' => 1, 'slug' => '/about'],
            2 => (object) ['uid' => 2, 'slug' => '/only-translated', 'l18n_cfg' => 1],
            3 => (object) ['uid' => 3, 'slug' => '/translated-only', 'l18n_cfg' => 2],
            4 => (object) ['uid' => 4, 'doktype' => 3, 'url' => 't3://page?uid=1#c12'],
        ] ), [2 => [1 => '/nur-uebersetzt'], 3 => [1 => '/nur-mit-uebersetzung']] );
        $de = ['uid' => 1, 'source' => ''];
        $fr = ['uid' => 2, 'source' => ''];

        $this->assertSame( '/about#' . $anchor, $urls->page( 1, 'c12' ) );
        $this->assertSame( '/about#' . $anchor, $urls->page( 4 ) );

        // pages hidden in the default language are only linked in their translations
        $this->assertNull( $urls->page( 2 ) );
        $this->assertSame( '/nur-uebersetzt', $urls->page( 2, '', $de ) );
        $this->assertNull( $urls->page( 2, '', $fr ) );

        // pages hidden if they aren't translated aren't linked from other languages
        $this->assertSame( '/translated-only', $urls->page( 3 ) );
        $this->assertSame( '/nur-mit-uebersetzung', $urls->page( 3, '', $de ) );
        $this->assertNull( $urls->page( 3, '', $fr ) );
        $this->assertSame( '/about', $urls->page( 1, '', $fr ) );
    }


    public function testTreeDisablesTranslationsOfHiddenPages(): void
    {
        $tree = new Typo3\Tree( $this->pages( [
            1 => (object) ['uid' => 1, 'pid' => 0, 'hidden' => 1],
            2 => (object) ['uid' => 2, 'pid' => 0],
        ] ) );

        $this->assertSame( 'its page in the default language is hidden', $tree->restriction( (object) ['uid' => 11, 'pid' => 0, 'sys_language_uid' => 1, 'l10n_parent' => 1] ) );
        $this->assertNull( $tree->restriction( (object) ['uid' => 12, 'pid' => 0, 'sys_language_uid' => 1, 'l10n_parent' => 2] ) );
    }


    public function testUrlsSkipPagesWithoutPublicUrl(): void
    {
        $urls = new Typo3\Urls( $this->pages( [
            1 => (object) ['uid' => 1, 'doktype' => 1, 'slug' => '/public', 'fe_group' => '-1'],
            2 => (object) ['uid' => 2, 'doktype' => 1, 'slug' => '/members', 'fe_group' => '3'],
            3 => (object) ['uid' => 3, 'doktype' => Typo3\Tree::BE_SECTION, 'slug' => '/backend'],
            4 => (object) ['uid' => 4, 'doktype' => 254, 'slug' => '/folder'],
            5 => (object) ['uid' => 5, 'doktype' => 4, 'shortcut' => 2],
        ] ), [] );

        $this->assertSame( '/public', $urls->page( 1 ) );
        // links to restricted pages, backend sections and folders lead to nowhere in the frontend
        $this->assertNull( $urls->page( 2 ) );
        $this->assertNull( $urls->page( 3 ) );
        $this->assertNull( $urls->page( 4 ) );
        $this->assertNull( $urls->page( 5 ) );
    }


    public function testTreeSelectsPagesAndFindsParents(): void
    {
        $root = (object) ['uid' => 1, 'pid' => 0, 'is_siteroot' => 0];
        $site = (object) ['uid' => 2, 'pid' => 1, 'is_siteroot' => 1];
        $page = (object) ['uid' => 3, 'pid' => 2, 'is_siteroot' => 0];
        $orphan = (object) ['uid' => 4, 'pid' => 9];
        $tree = new Typo3\Tree( $this->pages( [1 => $root, 2 => $site, 3 => $page, 4 => $orphan,
            5 => (object) ['uid' => 5, 'pid' => 6], 6 => (object) ['uid' => 6, 'pid' => 5]] ) );

        $this->assertSame( [$page, $root], $tree->select( [3, 1] )->all() );
        $this->assertSame( '0000000000', $tree->order( $root ) );
        $this->assertSame( '000000000000000000010000000002', $tree->order( $page ) );
        $this->assertSame( $site, $tree->root( $page ) );
        $this->assertSame( $site, $tree->root( $site ) );
        $this->assertSame( $root, $tree->root( $root ) );
        $this->assertSame( $site, $tree->parent( $page ) );
        $this->assertNull( $tree->parent( $root ) );
        $this->assertNull( $tree->parent( $orphan ) );

        try {
            $tree->select( [3, 7, 8] );
            $this->fail( 'Missing pages must be rejected' );
        } catch( \InvalidArgumentException $e ) {
            $this->assertSame( 'TYPO3 page UID not found: 7, 8', $e->getMessage() );
        }

        try {
            $tree->order( $orphan );
            $this->fail( 'Missing parents must be rejected' );
        } catch( \RuntimeException $e ) {
            $this->assertSame( 'TYPO3 parent page [9] is missing from the source tree.', $e->getMessage() );
        }

        $this->expectExceptionMessage( 'Cycle detected in TYPO3 page tree at page [5].' );
        $tree->root( $tree->select( [5] )->first() );
    }


    public function testTreeUidsValidatesPageOptions(): void
    {
        $this->assertNull( Typo3\Tree::uids( [] ) );
        $this->assertSame( [3, 1], Typo3\Tree::uids( ['3', 1, '03', 3] ) );

        foreach( ['0', 'a', '-1', '1.5', ''] as $value )
        {
            try {
                Typo3\Tree::uids( ['2', $value] );
                $this->fail( "Invalid page UID \"{$value}\" must be rejected" );
            } catch( \InvalidArgumentException $e ) {
                $this->assertSame( "Invalid TYPO3 page UID: {$value}", $e->getMessage() );
            }
        }
    }


    public function testLanguagesMapsUidsAndGroupsTranslations(): void
    {
        $languages = new Typo3\Languages( [1 => ['lang' => 'de', 'domain' => ''], 2 => ['lang' => 'fr', 'domain' => '']], ['3:es', '01:da:https://example.dk/'] );

        $this->assertSame( ['lang' => 'da', 'domain' => 'example.dk'], $languages->get( 1 ) );
        $this->assertSame( ['lang' => 'fr', 'domain' => ''], $languages->get( 2 ) );
        $this->assertSame( ['lang' => 'es', 'domain' => ''], $languages->get( 3 ) );
        $this->assertNull( $languages->get( 4 ) );

        $result = $languages->translations( $this->rows( [
            (object) ['uid' => 10, 'l10n_parent' => 5, 'sys_language_uid' => '1', 'slug' => '/ueber'],
            (object) ['uid' => 11, 'l10n_parent' => 5, 'sys_language_uid' => 3, 'slug' => '/sobre'],
            (object) ['uid' => 12, 'l10n_parent' => 6, 'sys_language_uid' => 2, 'slug' => '/contact'],
            (object) ['uid' => 13, 'l10n_parent' => 6, 'sys_language_uid' => 4, 'slug' => '/x'],
            (object) ['uid' => 14, 'l10n_parent' => 7, 'sys_language_uid' => '7', 'slug' => '/y'],
            (object) ['uid' => 15, 'l10n_parent' => 7, 'sys_language_uid' => 4, 'slug' => '/z'],
            (object) ['uid' => 16, 'l10n_parent' => 6, 'sys_language_uid' => 2, 'slug' => '/contact-2'],
            (object) ['uid' => 17, 'l10n_parent' => 8, 'sys_language_uid' => 1],
        ] ) );

        // the first translation of a page in each language is used
        $this->assertSame( [4, 7], $result['unknown'] );
        $this->assertSame( 5, $result['count'] );
        $this->assertSame( [5 => [1 => '/ueber', 3 => '/sobre'], 6 => [2 => '/contact'], 8 => [1 => '']], $result['slugs'] );

        $this->expectExceptionMessage( 'Invalid TYPO3 language: 0:de' );
        new Typo3\Languages( [], ['0:de'] );
    }


    public function testSiteMapsRootsToDomains(): void
    {
        $site = new Typo3\Site( ['example.com:1', 'example.de:2'], fn() => [3 => 't3.org', 1 => 'other.org'] );

        $this->assertSame( '', $site->domain() );
        $this->assertSame( 'example.com', $site->domain( (object) ['uid' => '1'] ) );
        $this->assertSame( 'example.de', $site->domain( (object) ['uid' => 2] ) );
        $this->assertSame( 't3.org', $site->domain( (object) ['uid' => 3] ) );
        $this->assertSame( '', $site->domain( (object) ['uid' => 4] ) );
        $this->assertSame( 'https://example.com/fileadmin', $site->fileBase( '' ) );
        $this->assertSame( 'https://files.org', $site->fileBase( 'https://files.org' ) );

        // the domain records aren't used if there's a default domain
        $site = new Typo3\Site( ['example.org', 'example.de:2'], fn() => throw new \LogicException( 'Domains must not be read' ) );

        $this->assertSame( 'example.org', $site->domain() );
        $this->assertSame( 'example.org', $site->domain( (object) ['uid' => 3] ) );
        $this->assertSame( 'example.de', $site->domain( (object) ['uid' => 2] ) );
        $this->assertSame( 'https://example.org/fileadmin', $site->fileBase( '' ) );
        $this->assertSame( 'http://t3.org/fileadmin', ( new Typo3\Site( [], fn() => [5 => 'http://t3.org/'] ) )->fileBase( '' ) );

        $site = new Typo3\Site( ['example.de:0'], fn() => [] );

        $this->assertSame( 'example.de', $site->domain() );
        $this->assertSame( 'example.de', ( new Typo3\Site( ['example.de:'], fn() => throw new \LogicException( 'Domains must not be read' ) ) )->domain() );
        $this->assertSame( '', ( new Typo3\Site( [], fn() => [] ) )->fileBase( '' ) );
    }


    public function testDestinationsFindsExistingPages(): void
    {
        $root = Pages::create( ['name' => 'Home', 'path' => '', 'tag' => 'root', 'domain' => 'example.com'], [] );
        $about = Pages::create( ['name' => 'About', 'path' => 'about', 'domain' => 'example.com'], [] );
        $other = Pages::create( ['name' => 'About', 'path' => 'about', 'domain' => 'example.de'], [] );
        $trashed = Pages::create( ['name' => 'Old', 'path' => 'old', 'domain' => 'example.com'], [] );
        $trashed->page->delete();

        $t3Root = (object) ['uid' => 1, 'pid' => 0, 'slug' => '/'];
        $t3About = (object) ['uid' => 2, 'pid' => 1, 'slug' => '/about'];
        $t3Old = (object) ['uid' => 3, 'pid' => 1, 'slug' => '/old'];
        $t3Site = (object) ['uid' => 4, 'pid' => 1, 'is_siteroot' => 1, 'slug' => '/site'];
        $t3New = (object) ['uid' => 5, 'pid' => 1, 'slug' => '/new'];
        $domains = [];

        $destinations = new Typo3\Destinations( $this->pages( [$t3Root, $t3About, $t3Old, $t3Site, $t3New] ), function( $page ) use ( &$domains ) {
            $domains[] = $page->uid;
            return 'example.com';
        } );

        $this->assertSame( [1, 2, 3, 4, 5], $domains );
        $this->assertSame( $root->id, $destinations->find( $t3Root, 'example.com' )?->id );
        $this->assertSame( $root->id, $destinations->find( $t3Site, 'example.com' )?->id );
        $this->assertSame( $about->id, $destinations->find( $t3About, 'example.com' )?->id );
        $this->assertSame( $other->id, $destinations->find( $t3About, 'example.de' )?->id );
        $this->assertNull( $destinations->find( $t3New, 'example.com' ) );

        // trashed pages are restored by the lookup
        $this->assertSame( $trashed->id, $destinations->find( $t3Old, 'example.com' )?->id );
        $this->assertFalse( PageView::withTrashed()->findOrFail( $trashed->id )->trashed() );

        // remembered pages are used without lookup
        $destinations->put( 'example.com', 'new', $about );
        $this->assertSame( $about->id, $destinations->find( $t3New, 'example.com' )?->id );

        $destinations->forget( $t3New );
        $this->assertNull( $destinations->find( $t3New, 'example.com' ) );

        $about->page->delete();
        $destinations->forget( $t3About );
        $this->assertSame( $about->id, $destinations->find( $t3About, 'example.com' )?->id );
    }
}

<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Typo3;

use Aimeos\Cms\Concerns\Vanished;
use Aimeos\Cms\Import\Pages;
use Aimeos\Cms\Import\Tally;
use Aimeos\Cms\Import\Typo3;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Utils;
use Illuminate\Support\Collection;


/**
 * Imports the redirects of the TYPO3 redirects module as hidden redirect pages and disables the vanished ones.
 */
trait Redirects
{
    use Vanished;

    /** @var array<string, bool> URL paths of the redirects and TYPO3 pages which are kept by disableVanished() */
    protected array $redirectPaths = [];

    /** ID of the root page whose redirects of previous imports are disabled by disableVanished() */
    protected string $redirectRoot = '';


    /**
     * Imports the redirects of the TYPO3 redirects module as hidden pages redirecting to their target.
     *
     * Redirects for all hosts ("*") are imported for each domain, the ones for other hosts are skipped.
     * Regular expressions, redirects with query parameters and redirects whose URL is used by another
     * page can't be imported and are reported. Redirects of previous imports which aren't imported any
     * more, e.g. because they were deleted or disabled in TYPO3, are disabled.
     *
     * @param array<string, array{page: PageView, lang: string}> $roots Root pages and their languages by domain
     * @param Collection<int, mixed> $pages Imported TYPO3 pages whose URLs are never disabled
     */
    protected function importRedirects( array $roots, Collection $pages ): void
    {
        $tally = new Tally();
        $rewriter = $this->rewriter();
        $domain = $this->domain;
        $lang = $this->lang;
        $seen = array_fill_keys( array_keys( $roots ), [] );

        try
        {
            foreach( $this->redirectRecords( array_map( 'strval', array_keys( $roots ) ) ) as [$uid, $path, $domains, $target] )
            {
                if( ( $to = $this->redirectTarget( $rewriter, $target ) ) === '' )
                {
                    $this->warn( "  Skipped redirect [{$uid}]: /{$path} (target \"{$target}\" can't be resolved)" );
                    continue;
                }

                foreach( $domains as $name )
                {
                    // the first redirect of the URL is used like by TYPO3, redirects to themselves aren't imported
                    if( isset( $seen[$name][$path] ) || $to === '/' . $path ) {
                        continue;
                    }

                    $seen[$name][$path] = true;
                    $this->domain = $name;
                    $this->lang = $roots[$name]['lang'];

                    $data = ['to' => $to] + $this->pageData( $path, $path, Pages::STATUS_HIDDEN );
                    $result = $this->transaction( "redirect /{$path}", fn() => $this->saveLink( $path, $data, $roots[$name]['page'] ) ?? false );

                    if( $result === false ) {
                        $this->warn( "  Skipped redirect: /{$path} ({$name}, URL is used by another page)" );
                    } elseif( $result ) {
                        $tally->add( $result['status'] );
                        $this->info( '  ' . $result['status']->label() . ": Redirect /{$path} to {$to} ({$name})" );
                    }
                }
            }

            $known = $this->pagePaths( $pages );

            // redirects of previous imports which aren't in TYPO3 any more are disabled per domain
            foreach( $roots as $name => $root )
            {
                $this->domain = $name;
                $this->lang = $root['lang'];
                $this->redirectRoot = (string) $root['page']->id;
                $this->redirectPaths = $seen[$name] + $known;
                $this->disableVanished();
            }
        }
        finally
        {
            $this->domain = $domain;
            $this->lang = $lang;
            $this->redirectPaths = [];
        }

        $this->subtotals( $tally, 'redirects' );
    }


    /**
     * Returns the enabled redirects of the TYPO3 redirects module which can be imported.
     *
     * Regular expressions, redirects of the home page, redirects with query parameters and redirects
     * of hosts which aren't imported are skipped and reported. The redirects of a host are returned
     * before the ones of all hosts, each ordered by UID.
     *
     * @param array<int, string> $hosts Imported domains
     * @return \Generator<int, array{0: int, 1: string, 2: array<int, string>, 3: string}> UID, URL path, domains and target of the redirects
     */
    protected function redirectRecords( array $hosts ): \Generator
    {
        // like TYPO3, redirects of the host are used before the ones of all hosts ("*") with the same URL
        $all = $this->reader->redirects();
        $wildcard = fn( $redirect ) => in_array( trim( (string) ( $redirect->source_host ?? '' ) ), ['', '*'], true );

        foreach( $all->reject( $wildcard )->concat( $all->filter( $wildcard ) ) as $redirect )
        {
            $uid = (int) ( $redirect->uid ?? 0 );
            $source = (string) ( $redirect->source_path ?? '' );
            $host = strtolower( trim( (string) ( $redirect->source_host ?? '' ) ) );

            if( !empty( $redirect->is_regexp ) )
            {
                $this->warn( "  Skipped redirect [{$uid}]: {$source} (regular expressions can't be imported)" );
                continue;
            }

            // TYPO3 stores the paths like entered, encoded like "/%C3%BCber" or not
            if( ( $path = Typo3\Urls::slug( rawurldecode( $source ) ) ) === '' || str_contains( $path, '?' ) )
            {
                $reason = $path === '' ? 'the home page can\'t be redirected' : 'query parameters can\'t be imported';
                $this->warn( "  Skipped redirect [{$uid}]: {$source} ({$reason})" );
                continue;
            }

            $domains = in_array( $host, ['', '*'], true ) ? $hosts : ( in_array( $host, $hosts, true ) ? [$host] : [] );

            if( $domains === [] )
            {
                $this->warn( "  Skipped redirect [{$uid}]: {$host}/{$path} (host isn't imported)" );
                continue;
            }

            yield [$uid, $path, $domains, (string) ( $redirect->target ?? '' )];
        }
    }


    /**
     * Returns the URL of the TYPO3 redirect target like "t3://page?uid=1" or an empty string if it can't be resolved.
     */
    protected function redirectTarget( Typo3\Html $rewriter, string $target ): string
    {
        $url = $rewriter->href( $target );
        return $url !== '' && ( str_starts_with( $url, '/' ) || Utils::isValidUrl( $url, false ) ) ? $url : '';
    }


    /**
     * Returns the URL paths of the imported TYPO3 pages and their translations.
     *
     * @param Collection<int, mixed> $pages Imported TYPO3 pages
     * @return array<string, bool> URL paths without slashes as keys
     */
    protected function pagePaths( Collection $pages ): array
    {
        $paths = [];

        foreach( $pages as $page ) {
            $paths[Typo3\Urls::slug( $page->slug ?? '' )] = true;
        }

        foreach( $this->translated as $slugs )
        {
            foreach( $slugs as $slug ) {
                $paths[Typo3\Urls::slug( $slug )] = true;
            }
        }

        return $paths;
    }


    /**
     * Returns the URL paths of the redirects and TYPO3 pages of the current import for disableVanished().
     *
     * @return array<string, bool> URL paths without slashes as keys
     */
    protected function importedPaths(): array
    {
        return $this->redirectPaths;
    }


    /**
     * Limits the vanished pages to the redirects of previous imports below the root page.
     *
     * Redirects are hidden link pages whose name is their URL path, so link pages of TYPO3 pages aren't disabled.
     *
     * @param \Illuminate\Database\Eloquent\Builder<\Aimeos\Cms\Models\PageVariant> $query Query for the page variants
     * @return \Illuminate\Database\Eloquent\Builder<\Aimeos\Cms\Models\PageVariant> Limited query
     */
    protected function vanishedScope( \Illuminate\Database\Eloquent\Builder $query ): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where( 'to', '!=', '' )->where( 'status', Pages::STATUS_HIDDEN )->whereColumn( 'name', 'path' )
            ->whereHas( 'page', fn( $query ) => $query->where( 'parent_id', $this->redirectRoot ) );
    }


    /**
     * Returns why the pages of previous imports are disabled.
     */
    protected function vanishedReason(): string
    {
        return "the TYPO3 redirect isn't imported any more, e.g. it was deleted, disabled or expired";
    }


    /**
     * Returns the warning shown after disabling the pages of previous imports.
     *
     * @param int $count Number of disabled pages
     */
    protected function vanishedMessage( int $count ): string
    {
        return "Disabled {$count} redirects of previous imports which aren't in TYPO3 any more.";
    }


    /**
     * Prints the redirects of the TYPO3 redirects module which would be imported or disabled for a dry run.
     *
     * The targets aren't resolved because that imports the linked files, so redirects whose targets
     * can't be resolved are listed as imported and aren't listed as disabled.
     *
     * @param Collection<int, mixed> $pages Imported TYPO3 pages
     */
    protected function printRedirects( Collection $pages ): void
    {
        $hosts = $rootDomains = [];

        foreach( $pages->filter( fn( $page ) => $this->isRoot( $page ) ) as $t3Root )
        {
            $domain = $this->site->domain( $t3Root );

            foreach( $this->hostsOf( $t3Root ) as $host => $lang )
            {
                $hosts[$host] ??= $lang;
                $rootDomains[$host] ??= $domain;
            }
        }

        $seen = [];

        foreach( $this->redirectRecords( array_map( 'strval', array_keys( $hosts ) ) ) as [$uid, $path, $domains, $target] )
        {
            foreach( $domains as $name )
            {
                if( !isset( $seen[$name][$path] ) )
                {
                    $seen[$name][$path] = true;
                    $this->line( "Redirect [{$uid}] /{$path} to {$target} ({$name})" );
                }
            }
        }

        $known = $this->pagePaths( $pages );
        $domain = $this->domain;
        $lang = $this->lang;

        // redirects of previous imports below the existing root pages which would be disabled
        try
        {
            foreach( $hosts as $host => $hostLang )
            {
                if( !( $root = PageView::where( 'tag', Pages::TAG_ROOT )->where( 'domain', $rootDomains[$host] )->first() ) ) {
                    continue;
                }

                $this->domain = (string) $host;
                $this->lang = $hostLang;
                $this->redirectRoot = (string) $root->id;
                $this->redirectPaths = ( $seen[(string) $host] ?? [] ) + $known;
                $this->printVanished();
            }
        }
        finally
        {
            $this->domain = $domain;
            $this->lang = $lang;
            $this->redirectPaths = [];
        }
    }
}

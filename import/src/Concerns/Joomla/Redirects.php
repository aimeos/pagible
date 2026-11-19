<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Joomla;

use Aimeos\Cms\Import\Joomla;
use Aimeos\Cms\Import\Tally;
use Aimeos\Cms\Models\PageView;


/**
 * Imports the redirects of the Joomla "Redirect" component as hidden link pages below the root page.
 *
 * Requires the Shared and Menu traits used by the same class for the objects of the run and the link pages.
 */
trait Redirects
{
    /**
     * Imports the redirects of the Joomla "Redirect" component as hidden link pages below the root page.
     *
     * Redirects whose URL paths are used by imported pages or whose targets aren't supported were skipped by
     * Joomla\UrlAssignment already, which checks the targets the same way, see Joomla\Links::redirectTarget().
     */
    protected function importRedirects( PageView $root ): void
    {
        $tally = new Tally();
        $run = $this->run;

        $this->skippedRedirects();

        foreach( array_chunk( $run->urls->redirects(), static::PREFETCH_SIZE ) as $chunk )
        {
            // the pages using the URL paths are fetched at once instead of one query per redirect
            $this->prefetchPages( array_map( fn( Joomla\KeptRedirect $redirect ) => $redirect->path, $chunk ) );

            foreach( $chunk as $redirect )
            {
                $path = $redirect->path;
                $data = $run->pages->redirectData( $path, $this->redirectTarget( $redirect ) );
                $saved = $this->transaction( "redirect /{$path}", fn() => $this->linkPage( 'Skipped redirect', "/{$path}", $path, $data, $root ) );

                $tally->add( $saved['status'] ?? null );
            }
        }

        $this->subtotals( $tally, 'redirects' );
    }


    /**
     * Warns about the Joomla redirects skipped because their targets aren't supported, see Joomla\Urls::unsupportedRedirects().
     */
    protected function skippedRedirects(): void
    {
        foreach( $this->run->urls->unsupportedRedirects() as $redirect ) {
            $this->notice( 'Skipped redirect', "/{$redirect->path}", "its target {$redirect->url} isn't supported" );
        }
    }


    /**
     * Returns the URL the kept Joomla redirect is imported with, see Joomla\Links::redirectTarget().
     *
     * Joomla\UrlAssignment keeps only the redirects whose targets resolve by the same check, see Joomla\Urls::redirects().
     *
     * @return string Target URL
     * @throws \LogicException If the target of the kept redirect doesn't resolve
     */
    protected function redirectTarget( Joomla\KeptRedirect $redirect ): string
    {
        return $this->run->links->redirectTarget( $redirect )
            ?? throw new \LogicException( "Target {$redirect->url} of the kept redirect /{$redirect->path} doesn't resolve" );
    }
}

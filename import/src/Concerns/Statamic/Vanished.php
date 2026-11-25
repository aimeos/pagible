<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Statamic;


/**
 * Disables the pages of previous imports whose Statamic entries aren't imported any more.
 *
 * Pages of entries which were deleted, unpublished or aren't in the imported collections any more get no URL path
 * of the current import, see \Aimeos\Cms\Concerns\Vanished.
 */
trait Vanished
{
    use \Aimeos\Cms\Concerns\Vanished;

    /**
     * Returns the URL paths of all imported entries for disableVanished().
     *
     * @return array<string, bool> URL paths without slashes as keys
     */
    protected function importedPaths(): array
    {
        $paths = ['' => true] + $this->titlePaths;

        foreach( $this->paths as $path )
        {
            if( $path !== null ) {
                $paths[$path] = true;
            }
        }

        return $paths;
    }


    /**
     * Includes the redirect pages of entries which aren't imported any more.
     *
     * @param \Illuminate\Database\Eloquent\Builder<\Aimeos\Cms\Models\PageVariant> $query Query for the page variants
     * @return \Illuminate\Database\Eloquent\Builder<\Aimeos\Cms\Models\PageVariant> Unchanged query
     */
    protected function vanishedScope( \Illuminate\Database\Eloquent\Builder $query ): \Illuminate\Database\Eloquent\Builder
    {
        return $query;
    }


    /**
     * Returns why the pages of previous imports are disabled.
     */
    protected function vanishedReason(): string
    {
        return "its Statamic entry isn't imported any more, e.g. it was deleted or unpublished or its collection isn't imported";
    }


    /**
     * Returns the warning shown after disabling the pages of previous imports.
     *
     * @param int $count Number of disabled pages
     */
    protected function vanishedMessage( int $count ): string
    {
        return "Disabled {$count} pages of previous imports whose Statamic entries aren't imported any more.";
    }
}

<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Statamic;

use Aimeos\Cms\Utils;


/**
 * Reports the entries of dry runs of the Statamic import.
 */
trait Reports
{
    /**
     * Prints the entries which would be imported and the pages which would be disabled.
     */
    protected function printDryRun(): void
    {
        // the data containing the titles is fetched for a chunk of entries at once
        foreach( $this->reader->entries()->chunk( static::TREE_SIZE ) as $chunk )
        {
            foreach( $this->reader->withData( $chunk->collect() ) as $entry )
            {
                $title = $this->reader->title( $entry, $this->reader->data( $entry ) );

                if( ( $path = $this->paths[$entry->id] ?? null ) === null ) {
                    $this->titlePaths[$path = Utils::slugify( $title )] = true;
                }

                $this->line( "  [{$entry->id}] ({$entry->collection}) {$title} (/{$path})" );
            }
        }

        $this->printVanished();
        $this->info( 'Dry run complete. No changes were made.' );
    }
}

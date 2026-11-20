<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Drupal;

use Aimeos\Cms\Facades\Elements as ElementResource;
use Aimeos\Cms\Facades\Files as FileResource;
use Aimeos\Cms\Facades\Pages as PageResource;
use Aimeos\Cms\Facades\Variants as VariantResource;
use Aimeos\Cms\Import\Drupal;
use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Permission;
use Illuminate\Support\Collection;


/**
 * Finds the stale pages, translations, shared elements and files of previous Drupal imports and moves them to the trash.
 */
trait Prune
{
    /**
     * Lists the pages, translations and shared elements of previous imports which weren't updated by this import.
     *
     * They may have been deleted or unpublished in Drupal and are only moved to the trash if the
     * --prune option is given.
     */
    protected function reportStale(): void
    {
        $prune = new Drupal\Prune( $this->editor, fn( string $msg ) => $this->warn( $msg ) );
        $domains = array_unique( [$this->domain, ...$this->languages->domains()] );

        $stale = $prune->stale( $domains, $this->saved );
        $elements = $this->since === null ? $prune->elements( $domains, $this->blocks->saved(), $this->drupalDomain !== '' ) : Collection::make();

        if( $this->option( 'prune' ) && ( $ids = $prune->trashable( $stale, $elements, $this->createdFiles->values()->map( strval( ... ) )->all(), $this->failed ) ) ) {
            $this->trash( $ids );
        }
    }


    /**
     * Keeps the files created or used by this import for pruning the unused files of previous imports.
     */
    protected function keepsFiles(): bool
    {
        return (bool) $this->option( 'prune' );
    }


    /**
     * Moves the stale pages, translations, shared elements and files to the trash.
     *
     * @param array{pages: array<int, string>, translations: array<string, array<int, string>>, elements: array<int, string>, files: array<int, string>, count: int} $ids IDs of the pages, the page IDs of the translations by language, the IDs of the shared elements and files and the number of pages and translations
     */
    protected function trash( array $ids ): void
    {
        Permission::system( function() use ( $ids ) {
            foreach( array_chunk( $ids['pages'], Base::MAX_BULK ) as $chunk ) {
                PageResource::dropMany( $chunk );
            }

            foreach( $ids['translations'] as $lang => $pageIds )
            {
                foreach( array_chunk( $pageIds, Base::MAX_BULK ) as $chunk ) {
                    VariantResource::dropMany( $chunk, (string) $lang );
                }
            }

            foreach( array_chunk( $ids['elements'], Base::MAX_BULK ) as $chunk ) {
                ElementResource::dropMany( $chunk );
            }

            foreach( array_chunk( $ids['files'], Base::MAX_BULK ) as $chunk ) {
                FileResource::dropMany( $chunk );
            }
        } );

        $this->info( "Moved {$ids['count']} pages and translations, " . count( $ids['elements'] ) . ' shared elements and ' . count( $ids['files'] ) . ' files to the trash.' );
    }
}

<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests\Typo3;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\CmsWithMigrations;
use Tests\ImportTestAbstract;


/**
 * Base class of the TYPO3 import tests with the helpers creating the TYPO3 records.
 */
abstract class TestAbstract extends ImportTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;


    /**
     * Returns TYPO3 pages keyed by UID in the type used by the TYPO3 importer.
     *
     * @param array<int, mixed> $pages
     * @return Collection<int, mixed>
     */
    protected function pages( array $pages ): Collection
    {
        return Collection::make( $pages );
    }


    /**
     * Returns TYPO3 records in the type returned by the TYPO3 reader.
     *
     * @param array<int|string, mixed> $records
     * @return Collection<int|string, mixed>
     */
    protected function records( array $records ): Collection
    {
        return Collection::make( $records );
    }


    /**
     * Returns the backend layouts in the type returned by the TYPO3 reader.
     *
     * @param array<int|string, mixed> $layouts
     * @return Collection<int|string, mixed>
     */
    protected function layouts( array $layouts ): Collection
    {
        return Collection::make( $layouts );
    }


    /**
     * Returns the database rows in the type returned by the readers.
     *
     * @param array<int, \stdClass> $rows
     * @return Collection<int, \stdClass>
     */
    protected function rows( array $rows ): Collection
    {
        return Collection::make( $rows );
    }


    /**
     * Returns the TYPO3 sys_file records in the type returned by the TYPO3 reader.
     *
     * @param array<string, mixed> $files
     * @return Collection<string, mixed>
     */
    protected function sysFiles( array $files ): Collection
    {
        return Collection::make( $files );
    }
}

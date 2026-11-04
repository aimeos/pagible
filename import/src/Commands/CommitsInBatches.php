<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\DB;
use Aimeos\Cms\Scout;
use Illuminate\Database\DetectsConcurrencyErrors;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Commits the imported records in batches instead of separately.
 *
 * Each commit writes to disk, so committing every record separately slows down the import.
 * The records are still imported in their own nested transactions (savepoints), so failed
 * records don't affect the others. Records imported before an error are kept, except after
 * deadlocks and lock wait timeouts, which roll back the current batch and stop the import.
 * The published records are added to the search index together after each batch has been committed.
 * Batches of slow records (e.g. with downloads) are committed early to keep locks short.
 */
trait CommitsInBatches
{
    use DetectsConcurrencyErrors;

    /** Number of imported records committed together */
    protected int $batchSize = 50;
    /** Number of seconds after which the imported records are committed even if the batch isn't complete */
    protected int $batchSeconds = 10;
    protected int $batched = 0;
    protected int $batchLevel = 0;
    protected float $batchStarted = 0;


    /**
     * Executes the command within a transaction which is committed after every batch of records.
     */
    #[\Override]
    protected function execute( InputInterface $input, OutputInterface $output ): int
    {
        return Scout::defer( function() use ( $input, $output ) {
            $db = DB::connection();
            $db->beginTransaction();
            $this->batchLevel = $db->transactionLevel();
            $this->batched = 0;
            $this->batchStarted = microtime( true );

            try {
                return parent::execute( $input, $output );
            } catch( \Throwable $e ) {
                if( $this->causedByConcurrencyError( $e ) ) {
                    $this->abortBatch();
                }

                throw $e;
            } finally {
                while( $db->transactionLevel() >= $this->batchLevel ) {
                    $db->commit();
                }

                $this->batchLevel = 0;
            }
        } );
    }


    /**
     * Counts the imported record and commits the batch if it's complete or has taken too long.
     *
     * Must be called after the transaction of a record has been finished.
     * Records imported within the transaction of another record are not counted.
     */
    protected function commitBatch(): void
    {
        $db = DB::connection();

        if( !$this->batchLevel || $db->transactionLevel() !== $this->batchLevel ) {
            return;
        }

        if( ++$this->batched >= $this->batchSize || microtime( true ) - $this->batchStarted >= $this->batchSeconds ) {
            $this->commitNow();
        }
    }


    /**
     * Commits the batched records before the batch is complete, e.g. before files are downloaded.
     *
     * Must be called outside the transactions of the records, otherwise nothing is committed.
     */
    protected function commitNow(): void
    {
        $db = DB::connection();

        if( !$this->batched || !$this->batchLevel || $db->transactionLevel() !== $this->batchLevel ) {
            return;
        }

        $this->batched = 0;
        $this->batchStarted = microtime( true );
        $this->committing();
        $db->commit();
        Scout::flush();
        $db->beginTransaction();
    }


    /**
     * Rolls back the records of the current batch after a deadlock or lock wait timeout.
     *
     * Laravel doesn't roll back the savepoints of nested transactions on these errors and
     * MySQL already rolled back the whole transaction on deadlocks, so the batch can't be
     * committed anymore. Records of the batch which were imported before are lost too.
     */
    protected function abortBatch(): void
    {
        $db = DB::connection();

        if( !$this->batchLevel || $db->transactionLevel() < $this->batchLevel ) {
            return;
        }

        $this->error( 'Import stopped by a concurrent change in the database, the records imported since the last commit were rolled back. Run the import again' );
        // MySQL rolls back the whole transaction on deadlocks, including the savepoints of outer transactions
        $db->rollBack( $db->getPdo()->inTransaction() ? $this->batchLevel - 1 : 0 );
    }


    /**
     * Stores additional data with the batch before it's committed.
     */
    protected function committing(): void
    {
    }
}

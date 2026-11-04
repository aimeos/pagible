<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Counts the imported, updated and unchanged pages of the processed records.
 */
class Tally
{
    protected int $total = 0;
    protected int $processed = 0;
    protected int $updated = 0;
    protected int $unchanged = 0;


    /**
     * Counts the record by the status of its page.
     *
     * @param Status|null $status Status of the saved page or NULL if the record was skipped or failed
     */
    public function add( ?Status $status ): void
    {
        $this->total++;

        if( $status )
        {
            $this->processed++;
            $this->updated += (int) ( $status === Status::Updated );
            // reused pages are kept as they are
            $this->unchanged += (int) ( $status === Status::Unchanged || $status === Status::Reused );
        }
    }


    /**
     * Returns the number of counted records including skipped and failed ones.
     */
    public function total(): int
    {
        return $this->total;
    }


    /**
     * Returns the numbers of imported, updated and unchanged pages.
     *
     * @param string $label Plural name of the records
     * @param int|null $total Number of source records or NULL for the number of counted ones
     * @return string Summary, e.g. "1 imported, 2 updated, 3 unchanged (6/7 posts processed)."
     */
    public function summary( string $label, ?int $total = null ): string
    {
        $created = $this->processed - $this->updated - $this->unchanged;
        $total ??= $this->total;

        return "{$created} imported, {$this->updated} updated, {$this->unchanged} unchanged ({$this->processed}/{$total} {$label} processed).";
    }
}

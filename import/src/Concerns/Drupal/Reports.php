<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Drupal;

use Illuminate\Support\Collection;


/**
 * Reports the nodes of dry runs, the failures and the warnings of the Drupal import.
 */
trait Reports
{
    /** @var int Number of shown warnings */
    protected int $warnings = 0;


    /**
     * Lists the Drupal nodes which would be imported, only the ones changed since the --since date if given.
     *
     * @param Collection<int, \stdClass> $nodes Drupal nodes
     */
    protected function printDryRun( Collection $nodes ): void
    {
        $changed = $this->since !== null ? $this->fetchChanged( $nodes->pluck( 'nid' )->all(), $this->since ) : [];

        foreach( $nodes as $node )
        {
            if( $this->since === null || ( $changed[$node->nid] ?? static::UNKNOWN_TIME ) >= $this->since ) {
                $this->line( "  [{$node->nid}] {$node->title} ({$node->type})" );
            }
        }

        $this->info( 'Dry run complete. No changes were made.' );
    }


    /**
     * Returns the message shown at the end of the import with the number of failures and warnings, see Commands::summary().
     */
    protected function finished(): string
    {
        return "Finished with {$this->failed} failures and {$this->warnings} warnings.";
    }


    /**
     * Shows the warning and counts it for the summary at the end.
     *
     * @param string $string Warning message
     * @param 'v'|'vv'|'vvv'|'quiet'|'normal'|\Symfony\Component\Console\Output\OutputInterface::VERBOSITY_*|null $verbosity Verbosity level
     */
    public function warn( $string, $verbosity = null ): void
    {
        $this->warnings++;
        parent::warn( $string, $verbosity );
    }
}

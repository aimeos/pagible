<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Result of saving an imported record as page.
 */
enum Status: string
{
    /** New page created by this import */
    case Imported = 'imported';
    /** Page of a previous import with a new version */
    case Updated = 'updated';
    /** Page of a previous import without changes */
    case Unchanged = 'unchanged';
    /** Page which wasn't imported or was saved by this import before, only used as parent */
    case Reused = 'reused';


    /**
     * Returns the status as shown in the import output, e.g. "Imported".
     */
    public function label(): string
    {
        return ucfirst( $this->value );
    }
}

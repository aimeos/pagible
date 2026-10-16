<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Illuminate\Support\Collection;


/**
 * Maps the referenced elements and files of the model by their IDs.
 */
trait MapsReferences
{
    /**
     * Maps the elements by ID automatically.
     *
     * @return Collection<string, Element> List elements with ID as keys and element models as values
     */
    public function getElementsAttribute() : Collection
    {
        $this->relationLoaded( 'elements' ) ?: $this->load( 'elements' );
        return $this->getRelation( 'elements' )->pluck( null, 'id' );
    }


    /**
     * Maps the files by ID automatically.
     *
     * @return Collection<string, File> List files with ID as keys and file models as values
     */
    public function getFilesAttribute() : Collection
    {
        $this->relationLoaded( 'files' ) ?: $this->load( 'files' );
        return $this->getRelation( 'files' )->pluck( null, 'id' );
    }
}

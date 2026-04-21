<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Concerns;


trait HasChanged
{
    /**
     * @var array<string, mixed>|null
     */
    protected ?array $changeInfo = null;


    /**
     * Returns information about the changes that were made to the page, if available.
     *
     * @return array<string, mixed>|null
     */
    public function changed() : ?array
    {
        return $this->changeInfo;
    }


    /**
     * Sets information about the changes that were made to the page.
     *
     * @param array<string, mixed> $info
     * @return static
     */
    public function setChanged( array $info ) : static
    {
        $this->changeInfo = $info;
        return $this;
    }
}

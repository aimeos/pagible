<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Concerns;


trait HasChanges
{
    protected ?array $changeInfo = null;


    public function changes() : ?array
    {
        return $this->changeInfo;
    }


    public function setChanges( array $info ) : static
    {
        $this->changeInfo = $info;
        return $this;
    }
}

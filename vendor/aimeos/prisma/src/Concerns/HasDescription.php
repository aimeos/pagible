<?php

namespace Aimeos\Prisma\Concerns;


/**
 * Content description.
 */
trait HasDescription
{
    private ?string $description = null;


    /**
     * Returns the content description.
     *
     * @return string|null Content description
     */
    public function description() : ?string
    {
        return $this->description;
    }


    /**
     * Sets the content description.
     *
     * @param string|null $description Content description
     * @return self
     */
    public function withDescription( ?string $description ) : self
    {
        $this->description = $description;
        return $this;
    }
}

<?php

namespace Aimeos\Prisma\Responses;

use Aimeos\Prisma\Concerns\HasDescription;
use Aimeos\Prisma\Concerns\HasMeta;
use Aimeos\Prisma\Concerns\HasUsage;
use Aimeos\Prisma\Files\File;


/**
 * File based response.
 */
class FileResponse extends File
{
    use HasDescription, HasMeta, HasUsage;


    private ?\Closure $async = null;
    private int $retry = 5;


    /**
     * Create a file instance from an asynchronous closure.
     *
     * @param \Closure $closure Closure which returns the file content when invoked or NULL when not yet ready
     * @return static New instance
     */
    public static function fromAsync( \Closure $closure, int $retry = 5 ) : static
    {
        $instance = new static;
        $instance->async = $closure;
        $instance->retry = $retry;

        return $instance;
    }


    /**
     * Returns the binary content, waiting if necessary.
     *
     * @return string|null Binary content
     */
    public function binary() : ?string
    {
        return parent::binary() ?? $this->wait();
    }


    /**
     * Checks whether the file content is ready to be retrieved.
     *
     * @return bool True if the file content is ready, false otherwise
     */
    public function ready() : bool
    {
        if( !$this->async ) {
            return true;
        }

        $closure = $this->async;
        $this->binary = $closure();

        return (bool) $this->binary;
    }


    /**
     * Waits until the asynchronous file content is ready and returns it.
     *
     * @return string|null Binary content
     */
    protected function wait() : ?string
    {
        if( $this->binary ) {
            return $this->binary;
        }

        if( !( $closure = $this->async ) ) {
            return $this->binary();
        }

        while( ( $this->binary = $closure() ) === null ) {
            sleep( $this->retry );
        }

        return $this->binary;
    }
}

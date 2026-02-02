<?php

namespace Aimeos\Prisma\Responses;

use Aimeos\Prisma\Concerns\HasMeta;
use Aimeos\Prisma\Concerns\HasUsage;


/**
 * Embedding vector response.
 */
class VectorResponse
{
    use HasMeta, HasUsage;


    /** @var array<int, array<int, float>|null> */
    private array $vectors;


    final private function __construct()
    {
    }


    /**
     * Create a vector response instance.
     *
     * @param array<int, array<int, float>|null> $vectors Embedding vectors
     * @return self VectorResponse instance
     */
    public static function fromVectors( array $vectors ) : self
    {
        $instance = new self;
        $instance->vectors = $vectors;

        return $instance;
    }


    /**
     * Get the first embedding vector.
     *
     * @return array<int, float>|null First embedding vector
     */
    public function first() : ?array
    {
        return $this->vectors[0] ?? null;
    }


    /**
     * Get the embedding vectors.
     *
     * @return array<int, array<int, float>|null> Embedding vectors
     */
    public function vectors() : array
    {
        return $this->vectors;
    }
}

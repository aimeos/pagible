<?php

namespace Aimeos\Prisma\Contracts\Image;

use Aimeos\Prisma\Files\Image;
use Aimeos\Prisma\Responses\VectorResponse;


interface Vectorize
{
    /**
     * Creates embedding vectors of the images' content.
     *
     * @param array<int, Image> $images List of input image objects
     * @param int|null $size Size of the resulting vector or null for provider default
     * @param array<string, mixed> $options Provider specific options
     * @return VectorResponse Response vector object
     */
    public function vectorize( array $images, ?int $size = null, array $options = [] ) : VectorResponse;
}

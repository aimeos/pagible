<?php

namespace Aimeos\Prisma\Contracts\Audio;

use Aimeos\Prisma\Files\Audio;
use Aimeos\Prisma\Responses\VectorResponse;


interface Vectorize
{
    /**
     * Creates embedding vectors of the audio content.
     *
     * @param array<int, Audio> $audio List of input audio objects
     * @param int|null $size Size of the resulting vector or null for provider default
     * @param array<string, mixed> $options Provider specific options
     * @return VectorResponse Response vector object
     */
    public function vectorize( array $audio, ?int $size = null, array $options = [] ) : VectorResponse;
}

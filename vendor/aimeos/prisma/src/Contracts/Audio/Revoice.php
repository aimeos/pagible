<?php

namespace Aimeos\Prisma\Contracts\Audio;

use Aimeos\Prisma\Files\Audio;
use Aimeos\Prisma\Responses\FileResponse;


interface Revoice
{
    /**
     * Exchange the voice in an audio file.
     *
     * @param Audio $audio Input audio object
     * @param string $voice Voice name or identifier
     * @param array<string, mixed> $options Provider specific options
     * @return FileResponse Response file object
     */
    public function revoice( Audio $audio, string $voice, array $options = [] ) : FileResponse;
}

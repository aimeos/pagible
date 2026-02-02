<?php

namespace Aimeos\Prisma\Contracts\Audio;

use Aimeos\Prisma\Files\Audio;
use Aimeos\Prisma\Responses\TextResponse;


interface Describe
{
    /**
     * Describe the content of an audio.
     *
     * @param Audio $audio Input audio object
     * @param string|null $lang ISO language code the description should be generated in
     * @param array<string, mixed> $options Provider specific options
     * @return TextResponse Response text
     */
    public function describe( Audio $audio, ?string $lang = null, array $options = [] ) : TextResponse;
}
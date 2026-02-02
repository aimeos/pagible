<?php

namespace Aimeos\Prisma\Contracts\Audio;

use Aimeos\Prisma\Responses\FileResponse;


interface Speak
{
    /**
     * Converts text to speech.
     *
     * @param string $text Text to be converted to speech
     * @param string|null $voice Voice identifier for speech synthesis
     * @param array<string, mixed> $options Provider specific options
     * @return FileResponse Audio file response
     */
    public function speak( string $text, ?string $voice = null, array $options = [] ) : FileResponse;
}
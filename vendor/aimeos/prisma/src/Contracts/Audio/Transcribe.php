<?php

namespace Aimeos\Prisma\Contracts\Audio;

use Aimeos\Prisma\Files\Audio;
use Aimeos\Prisma\Responses\TextResponse;


interface Transcribe
{
    /**
     * Speech to text.
     *
     * @param Audio $audio Audio file to be transcribed
     * @param string|null $lang ISO language code of the audio content
     * @param array<string, mixed> $options Provider specific options
     * @return TextResponse Transcription text response
     */
    public function transcribe( Audio $audio, ?string $lang = null, array $options = [] ) : TextResponse;
}
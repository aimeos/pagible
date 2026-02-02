<?php

namespace Aimeos\Prisma\Contracts\Image;

use Aimeos\Prisma\Files\Image;
use Aimeos\Prisma\Responses\FileResponse;


interface Background
{
    /**
     * Replace image background with a background described by the prompt.
     *
     * @param Image $image Input image object
     * @param string $prompt Prompt describing the new background
     * @param array<string, mixed> $options Provider specific options
     * @return FileResponse Response file
     */
    public function background( Image $image, string $prompt, array $options = [] ) : FileResponse;
}
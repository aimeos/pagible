<?php

namespace Aimeos\Prisma\Contracts\Image;

use Aimeos\Prisma\Files\Image;
use Aimeos\Prisma\Responses\FileResponse;


interface Repaint
{
    /**
     * Repaint an image according to the prompt.
     *
     * @param Image $image Input image object
     * @param string $prompt Prompt describing the changes
     * @param array<string, mixed> $options Provider specific options
     * @return FileResponse Response file
     */
    public function repaint( Image $image, string $prompt, array $options = [] ) : FileResponse;
}
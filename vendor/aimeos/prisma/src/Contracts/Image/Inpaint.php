<?php

namespace Aimeos\Prisma\Contracts\Image;

use Aimeos\Prisma\Files\Image;
use Aimeos\Prisma\Responses\FileResponse;


interface Inpaint
{
    /**
     * Edit an image by inpainting an area defined by a mask according to a prompt.
     *
     * @param Image $image Input image object
     * @param Image $mask Input mask image object
     * @param string $prompt Prompt describing the changes
     * @param array<string, mixed> $options Provider specific options
     * @return FileResponse Response file
     */
    public function inpaint( Image $image, Image $mask, string $prompt, array $options = [] ) : FileResponse;
}
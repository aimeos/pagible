<?php

namespace Aimeos\Prisma\Contracts\Image;

use Aimeos\Prisma\Files\Image;
use Aimeos\Prisma\Responses\FileResponse;


interface Erase
{
    /**
     * Erase parts of the image.
     *
     * @param Image $image Input image object
     * @param Image $mask Mask image object
     * @param array<string, mixed> $options Provider specific options
     * @return FileResponse Response file
     */
    public function erase( Image $image, Image $mask, array $options = [] ) : FileResponse;
}
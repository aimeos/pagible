<?php

namespace Aimeos\Prisma\Contracts\Image;

use Aimeos\Prisma\Files\Image;
use Aimeos\Prisma\Responses\FileResponse;


interface Relocate
{
    /**
     * Place the foreground object on a new background.
     *
     * @param Image $image Input image with foreground object
     * @param Image $bgimage Background image
     * @param array<string, mixed> $options Provider specific options
     * @return FileResponse Response file
     */
    public function relocate( Image $image, Image $bgimage, array $options = [] ) : FileResponse;
}
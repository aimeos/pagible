<?php

namespace Aimeos\Prisma\Contracts\Image;

use Aimeos\Prisma\Files\Image;
use Aimeos\Prisma\Responses\FileResponse;


interface Isolate
{
    /**
     * Remove the image background.
     *
     * @param Image $image Input image object
     * @param array<string, mixed> $options Provider specific options
     * @return FileResponse Response file
     */
    public function isolate( Image $image, array $options = [] ) : FileResponse;
}
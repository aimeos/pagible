<?php

namespace Aimeos\Prisma\Contracts\Image;

use Aimeos\Prisma\Files\Image;
use Aimeos\Prisma\Responses\FileResponse;


interface Upscale
{
    /**
     * Scale up the image.
     *
     * @param Image $image Input image object
     * @param int $factor Upscaling factor between 2 and the maximum value supported by the provider
     * @param array<string, mixed> $options Provider specific options
     * @return FileResponse Response file
     */
    public function upscale( Image $image, int $factor, array $options = [] ) : FileResponse;
}
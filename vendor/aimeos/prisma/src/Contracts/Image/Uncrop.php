<?php

namespace Aimeos\Prisma\Contracts\Image;

use Aimeos\Prisma\Files\Image;
use Aimeos\Prisma\Responses\FileResponse;


interface Uncrop
{
    /**
     * Extend/outpaint the image.
     *
     * @param Image $image Input image object
     * @param int $top Number of pixels to extend to the top
     * @param int $right Number of pixels to extend to the right
     * @param int $bottom Number of pixels to extend to the bottom
     * @param int $left Number of pixels to extend to the left
     * @param array<string, mixed> $options Provider specific options
     * @return FileResponse Response file
     */
    public function uncrop( Image $image,  int $top, int $right, int $bottom, int $left, array $options = [] ) : FileResponse;
}
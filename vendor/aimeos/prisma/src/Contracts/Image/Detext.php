<?php

namespace Aimeos\Prisma\Contracts\Image;

use Aimeos\Prisma\Files\Image;
use Aimeos\Prisma\Responses\FileResponse;


interface Detext
{
    /**
     * Remove all text from the image.
     *
     * @param Image $image Input image object
     * @param array<string, mixed> $options Provider specific options
     * @return FileResponse Response file
     */
    public function detext( Image $image, array $options = [] ) : FileResponse;
}
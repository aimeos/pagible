<?php

namespace Aimeos\Prisma\Contracts\Image;

use Aimeos\Prisma\Files\Image;
use Aimeos\Prisma\Responses\TextResponse;


interface Describe
{
    /**
     * Describe the content of an image.
     *
     * @param Image $image Input image object
     * @param string|null $lang ISO language code the description should be generated in
     * @param array<string, mixed> $options Provider specific options
     * @return TextResponse Response text
     */
    public function describe( Image $image, ?string $lang = null, array $options = [] ) : TextResponse;
}
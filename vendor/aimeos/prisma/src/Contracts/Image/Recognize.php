<?php

namespace Aimeos\Prisma\Contracts\Image;

use Aimeos\Prisma\Files\Image;
use Aimeos\Prisma\Responses\TextResponse;


interface Recognize
{
    /**
     * Recognizes the text in the given image (OCR).
     *
     * @param Image $image Input image object
     * @param array<string, mixed> $options Provider specific options
     * @return TextResponse Response text object
     */
    public function recognize( Image $image, array $options = [] ) : TextResponse;
}
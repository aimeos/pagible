<?php

namespace Aimeos\Prisma\Files;

use Aimeos\Prisma\Exceptions\PrismaException;


/**
 * Image file content.
 */
class Image extends File
{
    /**
     * Returns the mime type.
     *
     * @return string|null Mime type
     */
    public function mimeType() : ?string
    {
        $mimeType = parent::mimeType();

        if( !str_starts_with( (string) $mimeType, 'image/' ) ) {
            throw new PrismaException( sprintf( 'Must be an image mime type, got "%1$s"', $mimeType ) );
        }

        return $mimeType;
    }


    /**
     * Sets the mime type.
     *
     * @param string|null $mimeType Mime type
     * @return static Image instance
     */
    protected function setMimeType( ?string $mimeType ) : static
    {
        if( $mimeType && !str_starts_with( (string) $mimeType, 'image/' ) ) {
            throw new PrismaException( sprintf( 'Must be an image mime type, got "%1$s"', $mimeType ) );
        }

        parent::setMimeType( $mimeType );
        return $this;
    }
}

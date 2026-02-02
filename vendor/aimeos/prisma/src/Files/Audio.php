<?php

namespace Aimeos\Prisma\Files;

use Aimeos\Prisma\Exceptions\PrismaException;


/**
 * Audio file content.
 */
class Audio extends File
{
    /**
     * Returns the mime type.
     *
     * @return string|null Mime type
     */
    public function mimeType() : ?string
    {
        $mimeType = parent::mimeType();

        if( !str_starts_with( (string) $mimeType, 'audio/' ) ) {
            throw new PrismaException( sprintf( 'Must be an audio mime type, got "%1$s"', $mimeType ) );
        }

        return $mimeType;
    }


    /**
     * Sets the mime type.
     *
     * @param string|null $mimeType Mime type
     * @return static Audio instance
     */
    protected function setMimeType( ?string $mimeType ) : static
    {
        if( $mimeType && !str_starts_with( (string) $mimeType, 'audio/' ) ) {
            throw new PrismaException( sprintf( 'Must be an audio mime type, got "%1$s"', $mimeType ) );
        }

        parent::setMimeType( $mimeType );
        return $this;
    }
}

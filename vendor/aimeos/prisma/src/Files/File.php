<?php

namespace Aimeos\Prisma\Files;

use Aimeos\Prisma\Exceptions\NotFoundException;
use Aimeos\Prisma\Exceptions\NotImplementedException;
use Aimeos\Prisma\Exceptions\PrismaException;


/**
 * The file content.
 */
class File
{
    protected ?string $url = null;
    protected ?string $base64 = null;
    protected ?string $binary = null;
    protected ?string $filename = null;
    protected ?string $mimeType = null;


    final protected function __construct()
    {
    }


    /**
     * Create a file instance from a base64 encoded string.
     *
     * @param string $base64 Base64 encoded file content
     * @param string|null $mimeType Optional mime type
     * @return static File instance
     */
    public static function fromBase64( string $base64, ?string $mimeType = null ) : static
    {
        $instance = new static;
        $instance->base64 = $base64;
        $instance->setMimeType( $mimeType );

        return $instance;
    }


    /**
     * Create a file instance from binary content.
     *
     * @param string $binary Binary file content
     * @param string|null $mimeType Optional mime type
     * @return static File instance
     */
    public static function fromBinary( string $binary, ?string $mimeType = null ) : static
    {
        $instance = new static;
        $instance->binary = $binary;
        $instance->setMimeType( $mimeType );

        return $instance;
    }


    /**
     * Create a file instance from a local file path.
     *
     * @param string $path Local file path
     * @param string|null $mimeType Optional mime type
     * @return static File instance
     */
    public static function fromLocalPath( string $path, ?string $mimeType = null ) : static
    {
        if( !( $content = file_get_contents( $path ) ) ) {
            throw new PrismaException( "Unable to read file from $path or it is empty" );
        }

        $instance = new static;
        $instance->binary = $content;
        $instance->filename = basename( $path );
        $instance->setMimeType( $mimeType );

        return $instance;
    }


    /**
     * Create a file instance from a Laravel storage path.
     *
     * @param string $path Storage file path
     * @param string|null $disk Optional storage disk name
     * @param string|null $mimeType Optional mime type
     * @return static File instance
     */
    public static function fromStoragePath( string $path, ?string $disk = null, ?string $mimeType = null ) : static
    {
        if( !class_exists( '\Illuminate\Support\Facades\Storage' ) ) {
            throw new NotImplementedException( 'Laravel storage facade is not available' );
        }

        $disk = \Illuminate\Support\Facades\Storage::disk( $disk );
        $content = $disk->get($path);

        if( !( $content = $disk->get( $path ) ) ) {
            throw new NotFoundException( sprintf( 'Laravel storage disk "%1$s" does not contain "%2$s" or the file is empty', $disk, $path ) );
        }

        $instance = new static;
        $instance->binary = $content;
        $instance->filename = basename( $path );
        $instance->setMimeType( $mimeType ?: $disk->mimeType( $path ) ?: null );

        return $instance;
    }


    /**
     * Create a file instance from a URL.
     *
     * @param string $url File URL
     * @param string|null $mimeType Optional mime type
     * @return static File instance
     */
    public static function fromUrl( string $url, ?string $mimeType = null ) : static
    {
        $instance = new static;
        $instance->url = $url;
        $instance->setMimeType( $mimeType );

        return $instance;
    }


    /**
     * Set the file name.
     *
     * @param string $name New file name
     * @return self File instance
     */
    public function as( string $name ) : self
    {
        $this->filename = $name;
        return $this;
    }


    /**
     * Returns the base64 encoded file content.
     *
     * @return string|null Base64 encoded content
     */
    public function base64() : ?string
    {
        if( !$this->base64 ) {
            $this->base64 = base64_encode( (string) $this->binary() );
        }

        return $this->base64;
    }


    /**
     * Returns the binary file content.
     *
     * @return string|null Binary content
     */
    public function binary() : ?string
    {
        if( $this->binary ) {
            return $this->binary;
        }

        if( $this->base64 ) {
            return $this->binary = base64_decode( (string) $this->base64 );
        }

        if( $this->url && !( $this->binary = file_get_contents( $this->url ) ?: null ) ) {
            throw new PrismaException( "Unable to fetch URL from {$this->url} or it is empty" );
        }

        return $this->binary;
    }


    /**
     * Returns the file name.
     *
     * @return string|null File name
     */
    public function filename() : ?string
    {
        return $this->filename;
    }


    /**
     * Returns the mime type.
     *
     * @return string|null Mime type
     */
    public function mimeType() : ?string
    {
        if( !$this->mimeType )
        {
            if( $this->binary || $this->base64 ) {
                $this->mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer( (string) $this->binary() ) ?: null;
            } elseif( $this->url && ( $content = file_get_contents( $this->url, false, null, 0, 255 ) ) ) {
                $this->mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer( (string) $content ) ?: null;
            }
        }

        return $this->mimeType;
    }


    /**
     * Returns the file URL.
     *
     * @return string|null File URL
     */
    public function url() : ?string
    {
        return $this->url;
    }


    /**
     * Sets the mime type.
     *
     * @param string|null $mimeType Mime type
     * @return self File instance
     */
    protected function setMimeType( ?string $mimeType ) : self
    {
        $this->mimeType = $mimeType;
        return $this;
    }
}
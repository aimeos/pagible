<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Exceptions\InvalidInputException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;


/**
 * Validation and handling of uploaded files, their MIME types and contents.
 */
class Uploads
{
    /**
     * Determines the MIME type of a file located at the given path or URL.
     *
     * @param string $path The file path or URL
     * @param string $disk Logical CMS disk ("public" or "private") of a stored file
     * @return string The MIME type of the file
     * @throws Exception If the file cannot be accessed or read
     */
    public static function mimetype( string $path, string $disk = 'public' ) : string
    {
        if( str_starts_with( $path, 'http') )
        {
            $response = SafeHttp::request( $path, ['stream' => true], ['Range' => 'bytes=0-299'] );

            if( !$response->successful() ) {
                throw new InvalidInputException( 'URL not accessible' );
            }

            $body = $response->toPsrResponse()->getBody();
            $buffer = $body->read( 300 );
            $body->close();
        }
        else
        {
            if( ( $path = FileStorage::normalizePath( $path ) ) === null ) {
                throw new Exception( 'Invalid file path' );
            }

            $stream = FileStorage::disk( $disk )->readStream( $path );

            if( !$stream ) {
                throw new Exception( 'File not accessible' );
            }

            if( ( $buffer = fread( $stream, 300 ) ) === false ) {
                fclose( $stream );
                throw new Exception( 'File not readable' );
            }

            fclose( $stream );
        }

        $finfo = new \finfo( FILEINFO_MIME_TYPE );

        if( ( $mime = $finfo->buffer( $buffer ) ) === false ) {
            throw new Exception( 'Failed to get mime type' );
        }

        return $mime;
    }


    /**
     * Returns a file extension that is safe to serve from the storage disk.
     *
     * Neutralizes dangerous uploads/restores (e.g. .php, .html, .phar) by replacing
     * extensions the web server may execute or serve as active content with "bin",
     * so user-supplied files cannot run as code or script.
     *
     * @param string|null $ext File extension (without leading dot)
     * @return string Safe file extension
     */
    public static function extension( ?string $ext ) : string
    {
        $ext = strtolower( (string) preg_replace( '/[^A-Za-z0-9]/', '', (string) $ext ) ) ?: 'bin';

        return match( true ) {
            in_array( $ext, ['htaccess', 'cgi', 'pht', 'phtml', 'phar', 'pl'], true ),
            str_starts_with( $ext, 'php' ),
            str_starts_with( $ext, 'asp' ),
            str_starts_with( $ext, 'jsp' ),
            str_contains( $ext, 'htm' ) => 'bin',
            default => $ext,
        };
    }


    /**
     * Validates a MIME type against configured allowed prefixes.
     *
     * @param string $mime The MIME type to validate
     * @return bool True if the MIME type is allowed, false otherwise
     */
    public static function isValidMimetype( string $mime ) : bool
    {
        $allowed = config( 'cms.upload.mimetypes', [] );
        return empty( $allowed ) || Str::startsWith( $mime, $allowed );
    }


    /**
     * Rejects MIME types which aren't allowed for uploads.
     *
     * @param string $mime MIME type to check
     * @param string $label Name of the checked item used in the error message
     * @param string $prefix Additionally required MIME type prefix, e.g. "image/"
     * @throws InvalidInputException If the MIME type isn't allowed
     */
    public static function checkMimetype( string $mime, string $label = 'File', string $prefix = '' ) : void
    {
        if( !str_starts_with( $mime, $prefix ) || !self::isValidMimetype( $mime ) ) {
            throw new InvalidInputException( sprintf( '%s type "%s" not allowed, permitted types: %s',
                $label, $mime, implode( ', ', config( 'cms.upload.mimetypes', [] ) ) ) );
        }
    }


    /**
     * Validates file upload size against configured limit.
     *
     * @param UploadedFile $upload The uploaded file to validate
     * @return bool True if the file size is within the limit, false otherwise
     */
    public static function isValidUpload( UploadedFile $upload ) : bool
    {
        return $upload->getSize() <= self::maxSize();
    }


    /**
     * Returns the configured maximum upload size in MB.
     *
     * @return float Maximum size in MB, zero or more
     */
    public static function maxMegabytes() : float
    {
        return max( 0, (float) config( 'cms.upload.filesize', 50 ) );
    }


    /**
     * Returns the configured maximum upload size in bytes.
     *
     * @return int Maximum size in bytes, zero or more
     */
    public static function maxSize() : int
    {
        return (int) ( self::maxMegabytes() * 1024 * 1024 );
    }


    /**
     * Writes binary content into a temporary upload and passes it to the callback.
     *
     * The temporary file is removed again after the callback returns.
     *
     * @param string $binary File content
     * @param string $filename Client file name of the upload
     * @param string|null $mime Client MIME type or NULL to guess it from the content
     * @param \Closure(UploadedFile): mixed $fn Receives the temporary upload
     * @return mixed Return value of the callback
     * @throws Exception If the temporary file can't be written
     */
    public static function upload( string $binary, string $filename, ?string $mime, \Closure $fn ) : mixed
    {
        if( ( $path = tempnam( sys_get_temp_dir(), 'cms' ) ) === false || file_put_contents( $path, $binary ) === false )
        {
            if( $path !== false ) {
                @unlink( $path );
            }

            throw new Exception( 'Unable to create temporary file' );
        }

        try {
            return $fn( new UploadedFile( $path, $filename, $mime, null, true ) );
        } finally {
            @unlink( $path );
        }
    }


    /**
     * Decompresses gzip content up to the maximum upload size, other content is returned unchanged.
     *
     * @param string $content Raw or gzip-compressed content
     * @return string Decompressed content
     * @throws InvalidInputException If the decompressed content exceeds the maximum upload size
     */
    public static function gunzip( string $content ) : string
    {
        if( !str_starts_with( $content, "\x1f\x8b" ) ) {
            return $content;
        }

        $max = self::maxSize();
        $result = @gzdecode( $content, $max + 1 );

        if( $result === false || strlen( $result ) > $max ) {
            throw new InvalidInputException( 'Decompressed SVG exceeds the maximum upload size' );
        }

        return $result;
    }


    /**
     * Sanitizes SVG/SVGZ content, returns NULL for non-SVG or invalid input.
     *
     * @param mixed $content Raw SVG or gzip-compressed SVGZ content
     * @return string|null Sanitized content (re-compressed if input was SVGZ), or NULL on failure
     */
    public static function cleanSvg( mixed $content ) : string|null
    {
        if( !$content || !is_string( $content ) ) {
            return null;
        }

        $isZip = str_starts_with( $content, "\x1f\x8b" );
        $sanitizer = new \enshrined\svgSanitize\Sanitizer();

        if( !( $content = self::gunzip( $content ) ) || !( $clean = $sanitizer->sanitize( $content ) ) ) {
            return null;
        }

        return ( $isZip ? gzencode( $clean ) : $clean ) ?: null;
    }


    /**
     * Validates a primary or preview upload before storage or image decoding.
     */
    public static function checkUpload( UploadedFile $upload, bool $preview = false ) : void
    {
        $label = $preview ? 'Preview' : 'File';

        if( !$upload->isValid() ) {
            throw new InvalidInputException( sprintf( 'Invalid %s upload', strtolower( $label ) ) );
        }

        if( !self::isValidUpload( $upload ) ) {
            throw new InvalidInputException( sprintf( '%s size of %s MB exceeds the maximum of %s MB',
                $label, round( $upload->getSize() / 1024 / 1024, 3 ), self::maxMegabytes() ) );
        }

        $mime = (string) $upload->getMimeType();

        self::checkMimetype( $mime, $label, $preview ? 'image/' : '' );
    }


    /**
     * Rejects raster images whose decoded dimensions exceed the configured limit.
     *
     * @param mixed $resource Uploaded image or stream resource of a downloaded temporary file
     */
    public static function checkPixels( mixed $resource ) : void
    {
        $path = $resource instanceof UploadedFile ? $resource->getRealPath() : null;

        if( is_resource( $resource ) ) {
            $path = stream_get_meta_data( $resource )['uri'] ?? null;
        }

        if( !is_string( $path ) || !( $info = @getimagesize( $path ) ) ) {
            throw new InvalidInputException( 'Invalid image' );
        }

        $max = max( 1, (int) config( 'cms.upload.maxpixels', 4096 * 4096 ) );
        $width = (int) $info[0];
        $height = (int) $info[1];

        if( $height < 1 || $width < 1 || $width > intdiv( $max, $height ) ) {
            throw new InvalidInputException( sprintf( 'Image exceeds the maximum size of %d pixels', $max ) );
        }
    }
}

<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Aimeos\Cms\Exception;
use Aimeos\Cms\FileStorage;
use Aimeos\Cms\Models\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Symfony\Component\Mime\MimeTypes;


/**
 * File helpers shared by the importers.
 */
class Files
{
    /**
     * Downloads the file, stores it and creates a File record with a published version.
     *
     * @param string $mime MIME type of the file
     * @param string $name Descriptive file name
     * @param string $path Remote URL of the file
     * @param string $editor Editor name for the file and its version
     * @param array{resource: resource, mime: string}|null $fetched File downloaded before by fetch(), closed afterwards
     * @param array{description?: array<string, string>, crop?: array{x: float, y: float, width: float, height: float}} $options
     *  Descriptions by language code and the area of raster images which is stored relative to the image size
     * @return File Published file
     */
    public static function download( string $mime, string $name, string $path, string $editor, ?array $fetched = null, array $options = [] ) : File
    {
        $file = self::init( $mime, $name, $editor, $options['description'] ?? [] );
        $resource = $fetched['resource'] ?? null;

        try
        {
            return self::save( $file, $editor, function() use ( $file, $mime, $name, $path, $fetched, $options, &$resource ) {
                if( $resource ) {
                    // the MIME type of the downloaded file is detected from the content like by fetchUrl()
                    $file->mime = $fetched['mime'] ?? $mime;
                } else {
                    $resource = $file->fetchUrl( self::unfragment( $path ) );
                }

                if( $mime === 'image/svg+xml' ) {
                    self::svg( $resource );
                }

                if( isset( $options['crop'] ) ) {
                    $resource = self::crop( $resource, (string) $file->mime, $options['crop'] );
                }

                $tmp = stream_get_meta_data( $resource )['uri'] ?? null;
                $filename = basename( rawurldecode( (string) parse_url( $path, PHP_URL_PATH ) ) ) ?: $name;

                if( !is_string( $tmp ) ) {
                    throw new Exception( 'Unable to create temporary file' );
                }

                $file->ingest( new UploadedFile( $tmp, $filename, $mime, null, true ) );
            }, fn() => $file->discard( true ) );
        }
        finally
        {
            if( is_resource( $resource ) ) {
                fclose( $resource );
            }
        }
    }


    /**
     * Creates a File record with a published version which references the file by its path.
     *
     * @param string $mime MIME type of the file
     * @param string $name Descriptive file name
     * @param string $path Remote URL or path of the file
     * @param string $editor Editor name for the file and its version
     * @param array{description?: array<string, string>} $options Descriptions by language code
     * @return File Published file
     */
    public static function reference( string $mime, string $name, string $path, string $editor, array $options = [] ) : File
    {
        $file = self::init( $mime, $name, $editor, $options['description'] ?? [] );

        // referenced files aren't stored by the import, so they must not be removed from the disk
        return self::save( $file, $editor, function() use ( $file, $path ) {
            $file->path = $path;
            $file->previews = [];
        }, fn() => $file->discard( false ) );
    }


    /**
     * Downloads the file into a temporary file, so it can be created later by download() without waiting for the download.
     *
     * @param string $url URL of the file
     * @return array{resource: resource, mime: string} Temporary file, which is removed when it's closed, and the MIME type detected from its content
     */
    public static function fetch( string $url ) : array
    {
        $file = new File();
        $resource = $file->fetchUrl( self::unfragment( $url ) );

        return ['resource' => $resource, 'mime' => $file->mime];
    }


    /**
     * Publishes a new version of the file with the new name.
     *
     * @param File $file File to rename
     * @param string $name New descriptive file name
     * @param string $editor Editor name for the file and its version
     * @return File Published file
     */
    public static function rename( File $file, string $name, string $editor ) : File
    {
        // publishing saves the new name together with the published version
        $file->name = $name;
        $file->editor = $editor;

        self::publish( $file, $editor );

        return $file;
    }


    /**
     * Creates a new version of the file and publishes it.
     *
     * @param File $file File with the values of the new version
     * @param string $editor Editor name of the version
     */
    protected static function publish( File $file, string $editor ) : void
    {
        $snapshot = File::snapshot( $file->toArray() );
        $version = $file->versions()->forceCreate( [
            'lang' => $file->lang,
            'data' => $snapshot['data'],
            'aux' => $snapshot['aux'],
            'editor' => $editor,
        ] );

        // publishing saves the latest version ID together with the published values
        $file->forceFill( ['latest_id' => $version->id] );
        $file->publish( $version );
    }


    /**
     * Crops the raster image to the area relative to the image size and returns the temporary file of the cropped image.
     *
     * Other files and images which can't be read are returned unchanged.
     *
     * @param resource $resource Temporary file of the image, closed if a cropped image is returned
     * @param string $mime MIME type of the image
     * @param array{x: float, y: float, width: float, height: float} $crop Area of the image relative to its size
     * @return resource Temporary file of the cropped image which is removed when it's closed
     */
    public static function crop( $resource, string $mime, array $crop )
    {
        $tmp = stream_get_meta_data( $resource )['uri'] ?? null;

        if( !str_starts_with( $mime, 'image/' ) || $mime === 'image/svg+xml' || !is_string( $tmp ) ) {
            return $resource;
        }

        try
        {
            $driver = '\\Intervention\\Image\\Drivers\\' . ucfirst( (string) config( 'cms.image.driver', 'gd' ) ) . '\\Driver';
            $image = ImageManager::withDriver( $driver )->read( $tmp );
            $width = $image->width();
            $height = $image->height();

            $x = (int) round( $width * $crop['x'] );
            $y = (int) round( $height * $crop['y'] );
            $w = max( 1, min( $width - $x, (int) round( $width * $crop['width'] ) ) );
            $h = max( 1, min( $height - $y, (int) round( $height * $crop['height'] ) ) );

            $data = (string) $image->crop( $w, $h, $x, $y )->encodeByMediaType( $mime );
        }
        catch( \Throwable )
        {
            // images whose format isn't supported are stored uncropped
            return $resource;
        }

        if( !( $cropped = tmpfile() ) || fwrite( $cropped, $data ) === false ) {
            return $resource;
        }

        rewind( $cropped );
        fclose( $resource );

        return $cropped;
    }


    /**
     * Returns the URL without fragment, e.g. the "#crop=..." added to distinguish the cropped images of the same file.
     */
    protected static function unfragment( string $url ): string
    {
        return (string) preg_replace( '/#.*$/s', '', $url );
    }


    /**
     * Returns a new file with the given values.
     *
     * @param string $mime MIME type of the file
     * @param string $name Descriptive file name
     * @param string $editor Editor name for the file
     * @param array<string, string> $description Descriptions of the file by language code
     * @return File New file which isn't saved yet
     */
    protected static function init( string $mime, string $name, string $editor, array $description = [] ) : File
    {
        $file = new File();
        $file->mime = $mime;
        $file->name = $name;
        $file->editor = $editor;

        if( $description = array_filter( array_map( 'trim', $description ), fn( $text ) => $text !== '' ) ) {
            $file->description = (object) $description;
        }

        return $file;
    }


    /**
     * Stores the file content, saves and publishes the file and removes its content again if that or the import transaction fails.
     *
     * @param File $file New file
     * @param string $editor Editor name of the version
     * @param \Closure(): void $store Adds the content or the path of the file
     * @param \Closure(): mixed $remove Removes what was stored for the file
     * @return File Published file
     */
    protected static function save( File $file, string $editor, \Closure $store, \Closure $remove ) : File
    {
        try
        {
            $store();
            $file->save();

            self::publish( $file, $editor );

            // stored files must not remain if the import transaction fails later, remote URLs are kept
            // Laravel 11 doesn't support rollback callbacks, so the files remain there
            if( method_exists( $conn = $file->getConnection(), 'afterRollBack' ) ) {
                $conn->afterRollBack( $remove );
            }

            return $file;
        }
        catch( \Throwable $e )
        {
            $remove();
            throw $e;
        }
    }


    /**
     * Returns the URL of a file path relative to the base URL.
     *
     * Absolute URLs are recognized by their scheme, so URLs with spaces or non-ASCII characters are absolute too.
     * URIs without authority like "mailto:", "tel:" or "data:" are returned unchanged as well, so are values
     * whose first path segment contains a colon, which isn't allowed in relative URLs by RFC 3986.
     * Scheme-relative URLs like "//cdn.example.com/a.jpg" use the scheme of the base URL or HTTPS.
     *
     * @param string $path Absolute URL, URI with scheme, scheme-relative URL or file path
     * @param string $base Base URL without trailing slash or empty string to return the path only
     * @return string Absolute URL or path without leading slash
     */
    public static function url( string $path, string $base ) : string
    {
        if( preg_match( '#^[a-z][a-z0-9+.\-]*:#i', $path ) ) {
            return $path;
        }

        if( str_starts_with( $path, '//' ) ) {
            return ( parse_url( $base, PHP_URL_SCHEME ) ?: 'https' ) . ':' . $path;
        }

        $path = ltrim( $path, '/' );

        return $base ? $base . '/' . $path : $path;
    }


    /**
     * Returns the content of a file stored on a Pagible disk, e.g. to read imported CSV files.
     *
     * Texts which aren't valid UTF-8 are converted from Windows-1252, the most common encoding of older CSV exports.
     *
     * @param string $id ID of the Pagible file
     * @param int $max Maximum file size in bytes
     * @return string|null Content of the file or NULL if it isn't stored on a disk, doesn't exist or is too large
     */
    public static function contents( string $id, int $max = 1048576 ) : ?string
    {
        if( !( $file = File::find( $id ) ) || preg_match( '#^https?://#i', $path = (string) $file->path ) ) {
            return null;
        }

        $disk = Storage::disk( FileStorage::diskName( (string) $file->disk ) );

        if( !$disk->exists( $path ) || $disk->size( $path ) > $max || ( $content = $disk->get( $path ) ) === null ) {
            return null;
        }

        $content = (string) preg_replace( '/^\xEF\xBB\xBF/', '', $content );

        return mb_check_encoding( $content, 'UTF-8' ) ? $content : mb_convert_encoding( $content, 'UTF-8', 'Windows-1252' );
    }


    /**
     * Returns the URL of the stored file used in HTML content.
     *
     * Files referenced by their remote URL keep it. Local managed files must remain portable across hosts and
     * preview ports, so only the path of their URL is used, remote disks keep their absolute URL.
     */
    public static function stored( File $file ) : string
    {
        $path = (string) $file->path;

        if( preg_match( '#^https?://#i', $path ) ) {
            return $path;
        }

        $disk = FileStorage::diskName( (string) $file->disk );
        $url = Storage::disk( $disk )->url( $path );

        if( config( 'filesystems.disks.' . $disk . '.driver' ) === 'local' ) {
            $url = parse_url( $url, PHP_URL_PATH ) ?: $url;
        }

        return $url;
    }


    /**
     * Guesses the MIME type from the file extension of a path or URL.
     *
     * @param string $path File path or URL
     * @return string MIME type, media types are preferred if the extension is ambiguous
     */
    public static function mime( string $path ) : string
    {
        $ext = strtolower( pathinfo( parse_url( $path, PHP_URL_PATH ) ?: '', PATHINFO_EXTENSION ) );
        $types = $ext !== '' ? MimeTypes::getDefault()->getMimeTypes( $ext ) : [];

        // media types are preferred, e.g. "video/mp4" over "application/mp4", but PDFs aren't images
        return current( preg_grep( '#^(audio|image|video)/(?!pdf$)#', $types ) ?: $types ) ?: 'application/octet-stream';
    }


    /**
     * Adds the XML declaration needed by fileinfo to recognize plain SVG markup.
     *
     * Also replaces the undeclared Adobe Illustrator namespace entities.
     *
     * @param resource $resource SVG file stream
     */
    protected static function svg( $resource ) : void
    {
        rewind( $resource );
        $content = stream_get_contents( $resource );

        if( !is_string( $content ) ) {
            throw new Exception( 'Unable to read SVG file' );
        }

        $normalized = (string) preg_replace( '/^\xEF\xBB\xBF/', '', $content );
        $normalized = strtr( $normalized, [
            '&ns_extend;' => 'http://ns.adobe.com/Extensibility/1.0/',
            '&ns_ai;' => 'http://ns.adobe.com/AdobeIllustrator/10.0/',
            '&ns_graphs;' => 'http://ns.adobe.com/Graphs/1.0/',
            '&ns_vars;' => 'http://ns.adobe.com/Variables/1.0/',
            '&ns_imrep;' => 'http://ns.adobe.com/ImageReplacement/1.0/',
            '&ns_sfw;' => 'http://ns.adobe.com/SaveForWeb/1.0/',
            '&ns_custom;' => 'http://ns.adobe.com/GenericCustomNamespace/1.0/',
            '&ns_adobe_xpath;' => 'http://ns.adobe.com/AdobeXPath/1.0/',
        ] );

        if( preg_match( '/^\s*<\?xml\b/i', $normalized ) !== 1 ) {
            $normalized = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $normalized;
        }

        if( $normalized !== $content )
        {
            rewind( $resource );

            if( !ftruncate( $resource, 0 ) || fwrite( $resource, $normalized ) !== strlen( $normalized ) ) {
                throw new Exception( 'Unable to normalize SVG file' );
            }
        }

        rewind( $resource );
    }
}

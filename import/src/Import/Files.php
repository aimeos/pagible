<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Aimeos\Cms\Exception;
use Aimeos\Cms\Models\File;
use Illuminate\Http\UploadedFile;
use Symfony\Component\Mime\MimeTypes;


/**
 * File helpers shared by the importers.
 */
class Files
{
    /**
     * Creates a File record with a published version.
     *
     * @param string $mime MIME type of the file
     * @param string $name Descriptive file name
     * @param string $path Remote URL or path of the file
     * @param string $editor Editor name for the file and its version
     * @param bool $download TRUE to download and store the file, FALSE to reference the path
     * @param array{resource: resource, mime: string}|null $fetched File downloaded before by fetch(), closed afterwards
     * @return File Published file
     */
    public static function create( string $mime, string $name, string $path, string $editor, bool $download, ?array $fetched = null ) : File
    {
        $file = new File();
        $resource = $fetched['resource'] ?? null;
        // referenced files aren't stored by the import, so they must not be removed from the disk
        $remove = fn() => $download ? $file->removePreviews()->removeFile() : $file->removePreviews();

        try
        {
            $file->mime = $mime;
            $file->name = $name;
            $file->editor = $editor;

            if( $download )
            {
                if( $resource ) {
                    // the MIME type of the downloaded file is detected from the content like by fetchUrl()
                    $file->mime = $fetched['mime'] ?? $mime;
                } else {
                    $resource = $file->fetchUrl( $path ) ?? throw new Exception( 'Unable to create temporary file' );
                }

                if( $mime === 'image/svg+xml' ) {
                    self::svg( $resource );
                }

                $tmp = stream_get_meta_data( $resource )['uri'] ?? null;
                $filename = basename( rawurldecode( (string) parse_url( $path, PHP_URL_PATH ) ) ) ?: $name;

                if( !is_string( $tmp ) ) {
                    throw new Exception( 'Unable to create temporary file' );
                }

                $file->ingest( new UploadedFile( $tmp, $filename, $mime, null, true ) );
            }
            else
            {
                $file->path = $path;
                $file->previews = [];
            }

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
        finally
        {
            if( is_resource( $resource ) ) {
                fclose( $resource );
            }
        }
    }


    /**
     * Downloads the file into a temporary file, so it can be created later by create() without waiting for the download.
     *
     * @param string $url URL of the file
     * @return array{resource: resource, mime: string} Temporary file, which is removed when it's closed, and the MIME type detected from its content
     */
    public static function fetch( string $url ) : array
    {
        $file = new File();
        $resource = $file->fetchUrl( $url ) ?? throw new Exception( 'Unable to create temporary file' );

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
     * Returns the URL of a file path relative to the base URL.
     *
     * @param string $path Absolute URL or file path
     * @param string $base Base URL without trailing slash or empty string to return the path only
     * @return string Absolute URL or path without leading slash
     */
    public static function url( string $path, string $base ) : string
    {
        if( filter_var( $path, FILTER_VALIDATE_URL ) ) {
            return $path;
        }

        $path = ltrim( $path, '/' );

        return $base ? $base . '/' . $path : $path;
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
